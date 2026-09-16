-- SmartQMS protected staff queue operations.
-- Apply after 20260820_initial_schema.sql. These functions are intentionally
-- executable only by the service role and are called by authenticated PHP
-- endpoints after the existing session, role, method, and CSRF checks pass.

begin;

create or replace function public.staff_call_next(p_actor_legacy_id integer)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_actor uuid;
  v_counter public.counters;
  v_ticket public.tickets;
  v_service_legacy integer;
begin
  select p.id into v_actor
  from public.profiles p
  join public.user_roles ur on ur.user_id = p.id
  where p.legacy_id = p_actor_legacy_id
    and p.active
    and ur.role in ('staff', 'admin', 'super_admin')
  limit 1;
  if v_actor is null then
    return jsonb_build_object('status', 'no_window');
  end if;

  select * into v_counter
  from public.counters
  where staff_user_id = v_actor and active
  order by created_at
  limit 1
  for update;
  if not found then return jsonb_build_object('status', 'no_window'); end if;
  if v_counter.status = 'closed' then return jsonb_build_object('status', 'closed_window'); end if;
  if v_counter.service_id is null then return jsonb_build_object('status', 'unassigned_service'); end if;
  if exists (
    select 1 from public.tickets
    where counter_id = v_counter.id and status = 'serving'
  ) then
    return jsonb_build_object('status', 'already_serving');
  end if;

  select * into v_ticket
  from public.tickets
  where branch_id = v_counter.branch_id
    and service_id = v_counter.service_id
    and status = 'waiting'
  order by priority_level desc, created_at asc, id asc
  limit 1
  for update skip locked;
  if not found then return jsonb_build_object('status', 'empty_queue'); end if;

  update public.tickets
  set status = 'serving', counter_id = v_counter.id,
      called_at = now(), served_at = now()
  where id = v_ticket.id and status = 'waiting'
  returning * into v_ticket;
  if not found then return jsonb_build_object('status', 'empty_queue'); end if;

  update public.counters set status = 'busy', updated_at = now()
  where id = v_counter.id;
  insert into public.queue_events(ticket_id, branch_id, event_type, actor_user_id, metadata)
  values (v_ticket.id, v_ticket.branch_id, 'called', v_actor,
          jsonb_build_object('counter_id', v_counter.id));
  select legacy_id into v_service_legacy from public.services where id = v_ticket.service_id;

  return jsonb_build_object(
    'status', 'success',
    'service_id', v_service_legacy,
    'counter_id', coalesce(v_counter.legacy_id::text, v_counter.id::text),
    'ticket', jsonb_build_object(
      'ticket_id', v_ticket.legacy_id,
      'provider_id', v_ticket.id,
      'ticket_number', v_ticket.ticket_number,
      'reference_number', v_ticket.reference_number,
      'status', v_ticket.status
    )
  );
end;
$$;

create or replace function public.staff_finish_ticket(
  p_actor_legacy_id integer,
  p_ticket_legacy_id integer,
  p_action text
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_actor uuid;
  v_counter public.counters;
  v_ticket public.tickets;
  v_status text;
  v_event text;
  v_wait numeric(8,2);
  v_service_seconds integer;
  v_service_legacy integer;
begin
  if p_action not in ('complete', 'skip', 'void') then
    raise exception 'Unsupported staff ticket action' using errcode = '22023';
  end if;
  select p.id into v_actor
  from public.profiles p
  join public.user_roles ur on ur.user_id = p.id
  where p.legacy_id = p_actor_legacy_id
    and p.active
    and ur.role in ('staff', 'admin', 'super_admin')
  limit 1;
  if v_actor is null then return jsonb_build_object('status', 'no_window'); end if;

  select * into v_counter from public.counters
  where staff_user_id = v_actor and active
  order by created_at limit 1 for update;
  if not found then return jsonb_build_object('status', 'no_window'); end if;

  select * into v_ticket from public.tickets
  where legacy_id = p_ticket_legacy_id
    and counter_id = v_counter.id
    and status = 'serving'
  limit 1 for update;
  if not found then return jsonb_build_object('status', 'ticket_not_found'); end if;

  if p_action = 'complete' then
    v_status := 'done';
    v_event := 'completed';
    v_wait := round(extract(epoch from (coalesce(v_ticket.served_at, v_ticket.called_at, now()) - v_ticket.created_at)) / 60.0, 2);
    v_service_seconds := greatest(0, extract(epoch from (now() - coalesce(v_ticket.served_at, v_ticket.called_at, now())))::integer);
    update public.tickets set status = v_status, completed_at = now()
    where id = v_ticket.id and status = 'serving';
    update public.ticket_predictions
    set actual_wait_minutes = v_wait,
        actual_service_seconds = v_service_seconds,
        evaluated_at = now()
    where ticket_id = v_ticket.id;
    if v_ticket.customer_id is not null then
      insert into public.notifications(ticket_id, user_id, message, type, channel, delivery_status, "read")
      values (v_ticket.id, v_ticket.customer_id,
              'Your service is complete. Please submit feedback when convenient.',
              'feedback_prompt', 'browser', 'pending', false);
    end if;
  elsif p_action = 'skip' then
    v_status := 'skipped';
    v_event := 'skipped';
    v_wait := 0;
    v_service_seconds := 0;
    update public.tickets
    set status = v_status, voided_at = now(), voided_reason = 'Client did not appear'
    where id = v_ticket.id and status = 'serving';
  else
    v_status := 'voided';
    v_event := 'voided';
    v_wait := 0;
    v_service_seconds := 0;
    update public.tickets
    set status = v_status, voided_at = now(), voided_reason = 'Voided manually by staff'
    where id = v_ticket.id and status = 'serving';
    if v_ticket.customer_id is not null then
      insert into public.notifications(ticket_id, user_id, message, type, channel, delivery_status, "read")
      values (v_ticket.id, v_ticket.customer_id,
              'Your SmartQMS ticket ' || v_ticket.ticket_number ||
              ' was voided by staff. Please contact the service window if you need assistance.',
              'turn_void', 'browser', 'pending', false);
    end if;
  end if;

  update public.counters set status = 'open', updated_at = now() where id = v_counter.id;
  insert into public.queue_events(ticket_id, branch_id, event_type, actor_user_id, metadata)
  values (v_ticket.id, v_ticket.branch_id, v_event, v_actor,
          jsonb_build_object('counter_id', v_counter.id, 'reason',
            case
              when p_action = 'skip' then 'Client did not appear'
              when p_action = 'void' then 'Voided manually by staff'
              else null
            end));
  select legacy_id into v_service_legacy from public.services where id = v_ticket.service_id;

  return jsonb_build_object(
    'status', 'success',
    'ticket_id', v_ticket.legacy_id,
    'provider_ticket_id', v_ticket.id,
    'ticket_number', v_ticket.ticket_number,
    'service_id', v_service_legacy,
    'counter_id', coalesce(v_counter.legacy_id::text, v_counter.id::text),
    'actual_wait_minutes', v_wait,
    'actual_service_seconds', v_service_seconds
  );
end;
$$;

create or replace function public.staff_set_counter_status(
  p_actor_legacy_id integer,
  p_status text
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_actor uuid;
  v_counter public.counters;
begin
  if p_status not in ('open', 'busy', 'paused', 'closed') then
    raise exception 'Invalid counter status' using errcode = '22023';
  end if;
  select p.id into v_actor
  from public.profiles p
  join public.user_roles ur on ur.user_id = p.id
  where p.legacy_id = p_actor_legacy_id
    and p.active
    and ur.role in ('staff', 'admin', 'super_admin')
  limit 1;
  if v_actor is null then return jsonb_build_object('status', 'no_window'); end if;

  select * into v_counter from public.counters
  where staff_user_id = v_actor and active
  order by created_at limit 1 for update;
  if not found then return jsonb_build_object('status', 'no_window'); end if;
  if p_status = 'closed' and exists (
    select 1 from public.tickets where counter_id = v_counter.id and status = 'serving'
  ) then
    return jsonb_build_object('status', 'ticket_in_progress');
  end if;

  update public.counters set status = p_status, updated_at = now() where id = v_counter.id;
  insert into public.queue_events(branch_id, event_type, actor_user_id, metadata)
  values (v_counter.branch_id, 'counter_' || p_status, v_actor,
          jsonb_build_object('counter_id', v_counter.id));
  return jsonb_build_object(
    'status', 'success',
    'counter_id', coalesce(v_counter.legacy_id::text, v_counter.id::text),
    'counter_status', p_status
  );
end;
$$;

revoke all on function public.staff_call_next(integer) from public, anon, authenticated;
revoke all on function public.staff_finish_ticket(integer, integer, text) from public, anon, authenticated;
revoke all on function public.staff_set_counter_status(integer, text) from public, anon, authenticated;
grant execute on function public.staff_call_next(integer) to service_role;
grant execute on function public.staff_finish_ticket(integer, integer, text) to service_role;
grant execute on function public.staff_set_counter_status(integer, text) to service_role;

commit;
