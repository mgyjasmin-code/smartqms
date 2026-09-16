-- SmartQMS Phase 5: safe live-queue projection and server-side ticket creation.

alter table public.tickets replica identity full;
alter table public.counters replica identity full;
alter table public.queue_events replica identity full;

create or replace function public.get_live_queue_snapshot(p_branch_id uuid default null)
returns jsonb
language sql
stable
security definer
set search_path = ''
as $$
  with visible_counters as (
    select c.*, s.name as service_name
    from public.counters c
    left join public.services s on s.id = c.service_id
    where c.active and (p_branch_id is null or c.branch_id = p_branch_id)
  ), window_rows as (
    select jsonb_build_object(
      'window_id', c.id, 'window_name', c.name, 'status', c.status,
      'service_id', c.service_id,
      'service_name', coalesce(c.service_name, 'No service assigned'),
      'average_service_minutes', c.average_service_minutes,
      'ticket_number', serving.ticket_number, 'client_type', serving.client_type
    ) as value
    from visible_counters c
    left join lateral (
      select t.ticket_number, t.client_type from public.tickets t
      where t.counter_id = c.id and t.status = 'serving'
      order by t.called_at desc nulls last, t.created_at desc limit 1
    ) serving on true
  ), next_rows as (
    select jsonb_build_object(
      'ticket_id', t.id, 'ticket_number', t.ticket_number,
      'service_id', t.service_id, 'service_name', s.name,
      'client_type', t.client_type, 'priority_level', t.priority_level,
      'created_at', t.created_at
    ) as value
    from public.tickets t join public.services s on s.id = t.service_id
    where t.status = 'waiting' and (p_branch_id is null or t.branch_id = p_branch_id)
    order by t.priority_level desc, t.created_at, t.id limit 10
  ), viewer as (
    select jsonb_build_object(
      'ticket_id', t.id, 'ticket_number', t.ticket_number,
      'service_name', s.name,
      'status', case when t.status = 'done' then 'completed' else t.status end,
      'people_ahead', (
        select count(*) from public.tickets ahead
        where ahead.branch_id = t.branch_id and ahead.service_id = t.service_id
          and ahead.status = 'waiting'
          and (ahead.priority_level > t.priority_level
            or (ahead.priority_level = t.priority_level
              and (ahead.created_at, ahead.id) < (t.created_at, t.id)))
      ),
      'predicted_wait_min', t.predicted_wait_minutes,
      'prediction_confidence', t.prediction_confidence,
      'model_version', t.model_version
    ) as value
    from public.tickets t join public.services s on s.id = t.service_id
    where t.customer_id = auth.uid() and t.status in ('waiting', 'serving', 'skipped')
    order by t.created_at desc limit 1
  )
  select jsonb_build_object(
    'windows', coalesce((select jsonb_agg(value) from window_rows), '[]'::jsonb),
    'next', coalesce((select jsonb_agg(value) from next_rows), '[]'::jsonb),
    'viewer_ticket', (select value from viewer)
  );
$$;

revoke all on function public.get_live_queue_snapshot(uuid) from public;
grant execute on function public.get_live_queue_snapshot(uuid) to anon, authenticated;

create or replace function public.server_create_queue_ticket(
  p_customer_id uuid, p_service_id uuid, p_branch_id uuid, p_client_type text,
  p_first_name text, p_last_name text, p_phone text,
  p_predicted_wait_minutes numeric default null,
  p_prediction_confidence numeric default null, p_model_version text default null
)
returns public.tickets
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_profile public.profiles;
  v_service public.services;
  v_ticket public.tickets;
  v_type text := lower(coalesce(p_client_type, 'regular'));
  v_year text := to_char(current_date, 'YYYY');
  v_reference text;
  v_ticket_number text;
  v_year_sequence bigint;
  v_day_sequence bigint;
begin
  if auth.role() <> 'service_role' then
    raise exception 'Service role required' using errcode = '42501';
  end if;
  if v_type not in ('regular', 'senior', 'pwd') then
    raise exception 'Invalid client type' using errcode = '22023';
  end if;
  select * into v_profile from public.profiles where id = p_customer_id for update;
  if not found then raise exception 'Customer profile not found' using errcode = 'P0002'; end if;
  select * into v_service from public.services where id = p_service_id and active;
  if not found then raise exception 'Active service not found' using errcode = 'P0002'; end if;
  if not exists (select 1 from public.branches where id = p_branch_id and active) then
    raise exception 'Active branch not found' using errcode = 'P0002';
  end if;
  if v_service.priority_only and v_type = 'regular' then
    raise exception 'Service is restricted to priority clients' using errcode = '22023';
  end if;
  if exists (
    select 1 from public.tickets
    where customer_id = p_customer_id and status in ('waiting', 'serving', 'skipped')
  ) then
    raise exception 'Customer already has an active ticket' using errcode = '23505';
  end if;

  update public.profiles
  set first_name = trim(p_first_name), last_name = trim(p_last_name),
      phone = nullif(trim(p_phone), ''), client_type = v_type, updated_at = now()
  where id = p_customer_id;

  perform pg_advisory_xact_lock(hashtext('smartqms-ticket-' || v_year));
  select count(*) + 1 into v_year_sequence from public.tickets
  where extract(year from created_at) = extract(year from current_date);
  select count(*) + 1 into v_day_sequence from public.tickets
  where created_at::date = current_date;
  v_reference := 'BHC-' || v_year || '-' || lpad(v_year_sequence::text, 4, '0');
  v_ticket_number := 'A-' || lpad(v_day_sequence::text, 3, '0');

  insert into public.tickets (
    ticket_number, reference_number, service_id, branch_id, customer_id,
    customer_name, customer_phone, queue_mode, client_type, priority_level,
    predicted_wait_minutes, prediction_confidence, model_version
  ) values (
    v_ticket_number, v_reference, v_service.id, p_branch_id, p_customer_id,
    trim(p_first_name || ' ' || p_last_name), nullif(trim(p_phone), ''),
    v_service.queue_mode, v_type, case when v_type in ('senior', 'pwd') then 1 else 0 end,
    p_predicted_wait_minutes, p_prediction_confidence, p_model_version
  ) returning * into v_ticket;

  insert into public.ticket_predictions (
    ticket_id, queue_length, hour_of_day, day_of_week, service_type_value,
    client_type_value, active_counters, average_service_minutes,
    predicted_wait_minutes, prediction_confidence, source, model_version
  ) values (
    v_ticket.id,
    (select count(*) from public.tickets t where t.service_id = p_service_id and t.status = 'waiting'),
    extract(hour from current_timestamp)::smallint,
    extract(dow from current_timestamp)::smallint,
    v_service.ml_value,
    case v_type when 'senior' then 1 when 'pwd' then 2 else 0 end,
    greatest(1, (select count(*) from public.counters c where c.branch_id = p_branch_id
      and c.active and c.status in ('open', 'busy') and (c.service_id is null or c.service_id = p_service_id))),
    coalesce((select avg(c.average_service_minutes) from public.counters c
      where c.branch_id = p_branch_id and c.active), 5),
    p_predicted_wait_minutes, p_prediction_confidence,
    case when p_prediction_confidence is null then 'fallback' else 'ml' end,
    coalesce(p_model_version, 'fallback-v1')
  );
  insert into public.queue_events (ticket_id, branch_id, event_type, actor_user_id, metadata)
  values (v_ticket.id, v_ticket.branch_id, 'ticket_created', p_customer_id,
          jsonb_build_object('source', 'php_server'));
  return v_ticket;
end;
$$;

revoke all on function public.server_create_queue_ticket(
  uuid, uuid, uuid, text, text, text, text, numeric, numeric, text
) from public, anon, authenticated;
grant execute on function public.server_create_queue_ticket(
  uuid, uuid, uuid, text, text, text, text, numeric, numeric, text
) to service_role;
