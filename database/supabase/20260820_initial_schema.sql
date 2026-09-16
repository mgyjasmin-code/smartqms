-- SmartQMS Supabase development schema
-- Apply only to a Supabase development project after reviewing the values
-- marked for local installation configuration. This file does not modify the
-- existing MySQL/MariaDB schema.

begin;

create extension if not exists pgcrypto;

create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  legacy_id integer unique,
  first_name text not null default '',
  last_name text not null default '',
  middle_name text,
  full_name text generated always as (
    btrim(first_name || ' ' || coalesce(nullif(middle_name, '') || ' ', '') || last_name)
  ) stored,
  phone text,
  client_type text not null default 'regular'
    check (client_type in ('regular', 'senior', 'pwd')),
  active boolean not null default true,
  verified boolean not null default false,
  must_change_password boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.user_roles (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  role text not null check (role in ('customer', 'staff', 'admin', 'super_admin')),
  created_at timestamptz not null default now(),
  unique (user_id, role)
);

create table if not exists public.services (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  code text not null unique,
  name text not null,
  description text,
  ml_value smallint not null,
  queue_mode text not null default 'central'
    check (queue_mode in ('central', 'specialized')),
  priority_only boolean not null default false,
  active boolean not null default true,
  display_order smallint not null default 0,
  created_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.branches (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  name text not null,
  address text not null default '',
  active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.staff_branch_assignments (
  id uuid primary key default gen_random_uuid(),
  staff_user_id uuid not null references auth.users(id) on delete cascade,
  branch_id uuid not null references public.branches(id) on delete cascade,
  active boolean not null default true,
  assigned_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  unique (staff_user_id, branch_id)
);

create table if not exists public.counters (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  branch_id uuid not null references public.branches(id) on delete restrict,
  name text not null,
  location_description text,
  counter_type text not null default 'shared'
    check (counter_type in ('shared', 'specialized')),
  service_id uuid references public.services(id) on delete set null,
  staff_user_id uuid references auth.users(id) on delete set null,
  status text not null default 'closed'
    check (status in ('open', 'busy', 'paused', 'closed')),
  priority_enabled boolean not null default true,
  active boolean not null default true,
  average_service_minutes numeric(8,2) not null default 10
    check (average_service_minutes >= 0 and average_service_minutes <= 480),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (branch_id, name)
);

create unique index if not exists counters_active_staff_unique
  on public.counters (staff_user_id)
  where staff_user_id is not null and active;

create table if not exists public.staff_service_capabilities (
  id uuid primary key default gen_random_uuid(),
  staff_user_id uuid not null references auth.users(id) on delete cascade,
  service_id uuid not null references public.services(id) on delete cascade,
  active boolean not null default true,
  assigned_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  unique (staff_user_id, service_id)
);

create table if not exists public.tickets (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  ticket_number text not null,
  reference_number text not null unique,
  service_id uuid not null references public.services(id) on delete restrict,
  branch_id uuid not null references public.branches(id) on delete restrict,
  customer_id uuid references auth.users(id) on delete set null,
  customer_name text not null,
  customer_phone text,
  counter_id uuid references public.counters(id) on delete set null,
  queue_mode text not null default 'central'
    check (queue_mode in ('central', 'specialized')),
  client_type text not null default 'regular'
    check (client_type in ('regular', 'senior', 'pwd')),
  priority_level smallint not null default 0
    check (priority_level between 0 and 9),
  status text not null default 'waiting'
    check (status in ('waiting', 'serving', 'done', 'skipped', 'voided')),
  qr_code_path text,
  predicted_wait_minutes numeric(8,2)
    check (predicted_wait_minutes is null or predicted_wait_minutes between 0 and 480),
  prediction_confidence numeric(6,5)
    check (prediction_confidence is null or prediction_confidence between 0 and 1),
  model_version text,
  created_at timestamptz not null default now(),
  called_at timestamptz,
  served_at timestamptz,
  completed_at timestamptz,
  voided_at timestamptz,
  voided_reason text
);

create index if not exists tickets_branch_status_priority_fifo
  on public.tickets (branch_id, status, priority_level desc, created_at, id);
create index if not exists tickets_service_status_priority_fifo
  on public.tickets (service_id, status, priority_level desc, created_at, id);
create index if not exists tickets_customer_status
  on public.tickets (customer_id, status, created_at desc);
create index if not exists tickets_display_number_created
  on public.tickets (ticket_number, created_at desc);
create unique index if not exists tickets_one_active_per_customer
  on public.tickets (customer_id)
  where customer_id is not null and status in ('waiting', 'serving', 'skipped');

create table if not exists public.ticket_predictions (
  id uuid primary key default gen_random_uuid(),
  ticket_id uuid not null unique references public.tickets(id) on delete cascade,
  queue_length integer,
  people_ahead integer,
  open_counters integer,
  priority_count integer,
  hour_of_day smallint check (hour_of_day between 0 and 23),
  day_of_week smallint check (day_of_week between 0 and 6),
  service_ml_value smallint,
  client_type_value smallint,
  average_service_minutes numeric(8,2),
  predicted_wait_minutes numeric(8,2),
  prediction_confidence numeric(6,5),
  actual_wait_minutes numeric(8,2),
  actual_service_seconds integer,
  source text not null default 'fallback',
  model_version text,
  created_at timestamptz not null default now(),
  evaluated_at timestamptz
);

create table if not exists public.queue_events (
  id uuid primary key default gen_random_uuid(),
  ticket_id uuid references public.tickets(id) on delete set null,
  branch_id uuid references public.branches(id) on delete set null,
  event_type text not null,
  actor_user_id uuid references auth.users(id) on delete set null,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists queue_events_ticket_created
  on public.queue_events (ticket_id, created_at desc);
create index if not exists queue_events_branch_created
  on public.queue_events (branch_id, created_at desc);

create table if not exists public.notifications (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  ticket_id uuid not null references public.tickets(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  message text not null,
  type text,
  channel text not null default 'browser'
    check (channel in ('browser', 'sms', 'both')),
  delivery_status text not null default 'pending'
    check (delivery_status in ('pending', 'sent', 'failed')),
  read boolean not null default false,
  sent_at timestamptz not null default now()
);

create index if not exists notifications_user_unread
  on public.notifications (user_id, read, sent_at desc, id desc);

create table if not exists public.feedback (
  id uuid primary key default gen_random_uuid(),
  legacy_id integer unique,
  ticket_id uuid not null unique references public.tickets(id) on delete restrict,
  user_id uuid not null references auth.users(id) on delete restrict,
  counter_id uuid references public.counters(id) on delete set null,
  service_id uuid references public.services(id) on delete set null,
  rating smallint not null check (rating between 1 and 5),
  comment text,
  submitted_at timestamptz not null default now()
);

create or replace function public.has_app_role(required_roles text[])
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select exists (
    select 1
    from public.user_roles ur
    where ur.user_id = auth.uid()
      and ur.role = any(required_roles)
  );
$$;

create or replace function public.staff_has_branch(target_branch uuid)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select public.has_app_role(array['admin', 'super_admin'])
    or exists (
      select 1
      from public.staff_branch_assignments sba
      where sba.staff_user_id = auth.uid()
        and sba.branch_id = target_branch
        and sba.active
    );
$$;

revoke all on function public.has_app_role(text[]) from public;
revoke all on function public.staff_has_branch(uuid) from public;
grant execute on function public.has_app_role(text[]) to authenticated;
grant execute on function public.staff_has_branch(uuid) to authenticated;

create or replace function public.handle_new_auth_user()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  insert into public.profiles (id, first_name, last_name, phone)
  values (
    new.id,
    coalesce(new.raw_user_meta_data ->> 'first_name', ''),
    coalesce(new.raw_user_meta_data ->> 'last_name', ''),
    nullif(new.raw_user_meta_data ->> 'phone', '')
  )
  on conflict (id) do nothing;

  insert into public.user_roles (user_id, role)
  values (new.id, 'customer')
  on conflict (user_id, role) do nothing;
  return new;
end;
$$;

drop trigger if exists smartqms_auth_user_created on auth.users;
create trigger smartqms_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_auth_user();

create or replace function public.create_queue_ticket(
  p_service_id uuid,
  p_branch_id uuid,
  p_client_type text default 'regular'
)
returns public.tickets
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_profile public.profiles;
  v_service public.services;
  v_branch public.branches;
  v_ticket public.tickets;
  v_reference text;
  v_ticket_number text;
  v_year text := to_char(now(), 'YYYY');
  v_day text := to_char(now(), 'YYYYMMDD');
  v_year_sequence integer;
  v_day_sequence integer;
  v_type text := lower(coalesce(p_client_type, 'regular'));
begin
  if auth.uid() is null or not public.has_app_role(array['customer']) then
    raise exception 'Customer authentication is required' using errcode = '42501';
  end if;
  if v_type not in ('regular', 'senior', 'pwd') then
    raise exception 'Invalid client type' using errcode = '22023';
  end if;

  select * into v_profile from public.profiles where id = auth.uid() and active;
  if not found then
    raise exception 'Active customer profile not found' using errcode = '42501';
  end if;
  select * into v_service from public.services where id = p_service_id and active;
  if not found then
    raise exception 'Active service not found' using errcode = '22023';
  end if;
  if v_service.priority_only and v_type = 'regular' then
    raise exception 'Service is restricted to priority clients' using errcode = '22023';
  end if;
  select * into v_branch from public.branches where id = p_branch_id and active;
  if not found then
    raise exception 'Active branch not found' using errcode = '22023';
  end if;

  perform pg_advisory_xact_lock(hashtext('smartqms-ticket-' || v_year));
  if exists (
    select 1 from public.tickets
    where customer_id = auth.uid() and status in ('waiting', 'serving', 'skipped')
  ) then
    raise exception 'Customer already has an active ticket' using errcode = '23505';
  end if;

  select coalesce(max((regexp_match(reference_number, '([0-9]+)$'))[1]::integer), 0) + 1
    into v_year_sequence
    from public.tickets
    where reference_number like 'BHC-' || v_year || '-%';
  select count(*) + 1 into v_day_sequence
    from public.tickets
    where created_at >= date_trunc('day', now())
      and created_at < date_trunc('day', now()) + interval '1 day';

  v_reference := 'BHC-' || v_year || '-' || lpad(v_year_sequence::text, 4, '0');
  v_ticket_number := 'A-' || lpad(v_day_sequence::text, 3, '0');

  insert into public.tickets (
    ticket_number, reference_number, service_id, branch_id, customer_id,
    customer_name, customer_phone, queue_mode, client_type, priority_level
  ) values (
    v_ticket_number, v_reference, v_service.id, v_branch.id, auth.uid(),
    v_profile.full_name, v_profile.phone, v_service.queue_mode, v_type,
    case when v_type in ('senior', 'pwd') then 1 else 0 end
  ) returning * into v_ticket;

  insert into public.queue_events (ticket_id, branch_id, event_type, actor_user_id, metadata)
  values (v_ticket.id, v_ticket.branch_id, 'ticket_created', auth.uid(),
          jsonb_build_object('source', 'customer_rpc'));
  return v_ticket;
end;
$$;

revoke all on function public.create_queue_ticket(uuid, uuid, text) from public;
grant execute on function public.create_queue_ticket(uuid, uuid, text) to authenticated;

create or replace function public.get_public_live_queue(p_branch_id uuid default null)
returns table (
  branch_id uuid,
  counter_id uuid,
  counter_name text,
  counter_status text,
  service_name text,
  ticket_number text,
  ticket_status text,
  waiting_count bigint
)
language sql
stable
security definer
set search_path = ''
as $$
  select
    c.branch_id,
    c.id,
    c.name,
    c.status,
    s.name,
    serving.ticket_number,
    serving.status,
    (
      select count(*)
      from public.tickets waiting
      where waiting.branch_id = c.branch_id
        and waiting.status = 'waiting'
        and (c.service_id is null or waiting.service_id = c.service_id)
    )
  from public.counters c
  left join public.services s on s.id = c.service_id
  left join lateral (
    select t.ticket_number, t.status
    from public.tickets t
    where t.counter_id = c.id and t.status = 'serving'
    order by t.called_at desc nulls last
    limit 1
  ) serving on true
  where c.active
    and (p_branch_id is null or c.branch_id = p_branch_id)
  order by c.name;
$$;

revoke all on function public.get_public_live_queue(uuid) from public;
grant execute on function public.get_public_live_queue(uuid) to anon, authenticated;

alter table public.profiles enable row level security;
alter table public.user_roles enable row level security;
alter table public.services enable row level security;
alter table public.branches enable row level security;
alter table public.staff_branch_assignments enable row level security;
alter table public.counters enable row level security;
alter table public.staff_service_capabilities enable row level security;
alter table public.tickets enable row level security;
alter table public.ticket_predictions enable row level security;
alter table public.queue_events enable row level security;
alter table public.notifications enable row level security;
alter table public.feedback enable row level security;

drop policy if exists profiles_read_own_or_admin on public.profiles;
create policy profiles_read_own_or_admin on public.profiles for select to authenticated
  using (id = auth.uid() or public.has_app_role(array['admin', 'super_admin']));
drop policy if exists profiles_update_own on public.profiles;
create policy profiles_update_own on public.profiles for update to authenticated
  using (id = auth.uid()) with check (id = auth.uid());

drop policy if exists roles_read_own_or_admin on public.user_roles;
create policy roles_read_own_or_admin on public.user_roles for select to authenticated
  using (user_id = auth.uid() or public.has_app_role(array['admin', 'super_admin']));
drop policy if exists roles_super_admin_insert on public.user_roles;
create policy roles_super_admin_insert on public.user_roles for insert to authenticated
  with check (public.has_app_role(array['super_admin']));
drop policy if exists roles_super_admin_update on public.user_roles;
create policy roles_super_admin_update on public.user_roles for update to authenticated
  using (public.has_app_role(array['super_admin']))
  with check (public.has_app_role(array['super_admin']));
drop policy if exists roles_super_admin_delete on public.user_roles;
create policy roles_super_admin_delete on public.user_roles for delete to authenticated
  using (public.has_app_role(array['super_admin']));

drop policy if exists services_read_active on public.services;
create policy services_read_active on public.services for select to anon, authenticated
  using (active);
drop policy if exists services_staff_read_all on public.services;
create policy services_staff_read_all on public.services for select to authenticated
  using (public.has_app_role(array['staff', 'admin', 'super_admin']));
drop policy if exists services_admin_manage on public.services;
create policy services_admin_manage on public.services for all to authenticated
  using (public.has_app_role(array['admin', 'super_admin']))
  with check (public.has_app_role(array['admin', 'super_admin']));

drop policy if exists branches_read_active on public.branches;
create policy branches_read_active on public.branches for select to anon, authenticated
  using (active);
drop policy if exists branches_staff_read_all on public.branches;
create policy branches_staff_read_all on public.branches for select to authenticated
  using (public.has_app_role(array['staff', 'admin', 'super_admin']));
drop policy if exists branches_admin_manage on public.branches;
create policy branches_admin_manage on public.branches for all to authenticated
  using (public.has_app_role(array['admin', 'super_admin']))
  with check (public.has_app_role(array['admin', 'super_admin']));

drop policy if exists staff_branches_read_scope on public.staff_branch_assignments;
create policy staff_branches_read_scope on public.staff_branch_assignments for select to authenticated
  using (staff_user_id = auth.uid() or public.has_app_role(array['admin', 'super_admin']));
drop policy if exists staff_branches_admin_manage on public.staff_branch_assignments;
create policy staff_branches_admin_manage on public.staff_branch_assignments for all to authenticated
  using (public.has_app_role(array['admin', 'super_admin']))
  with check (public.has_app_role(array['admin', 'super_admin']));

drop policy if exists counters_read_scope on public.counters;
create policy counters_read_scope on public.counters for select to authenticated
  using (public.staff_has_branch(branch_id));
drop policy if exists counters_admin_manage on public.counters;
create policy counters_admin_manage on public.counters for all to authenticated
  using (public.has_app_role(array['admin', 'super_admin']))
  with check (public.has_app_role(array['admin', 'super_admin']));

drop policy if exists capabilities_read_scope on public.staff_service_capabilities;
create policy capabilities_read_scope on public.staff_service_capabilities for select to authenticated
  using (staff_user_id = auth.uid() or public.has_app_role(array['admin', 'super_admin']));
drop policy if exists capabilities_admin_manage on public.staff_service_capabilities;
create policy capabilities_admin_manage on public.staff_service_capabilities for all to authenticated
  using (public.has_app_role(array['admin', 'super_admin']))
  with check (public.has_app_role(array['admin', 'super_admin']));

drop policy if exists tickets_customer_read_own on public.tickets;
create policy tickets_customer_read_own on public.tickets for select to authenticated
  using (customer_id = auth.uid());
drop policy if exists tickets_staff_read_branch on public.tickets;
create policy tickets_staff_read_branch on public.tickets for select to authenticated
  using (public.staff_has_branch(branch_id));

drop policy if exists predictions_read_scope on public.ticket_predictions;
create policy predictions_read_scope on public.ticket_predictions for select to authenticated
  using (exists (
    select 1 from public.tickets t
    where t.id = ticket_id
      and (t.customer_id = auth.uid() or public.staff_has_branch(t.branch_id))
  ));

drop policy if exists events_read_scope on public.queue_events;
create policy events_read_scope on public.queue_events for select to authenticated
  using (
    public.has_app_role(array['admin', 'super_admin'])
    or (branch_id is not null and public.staff_has_branch(branch_id))
    or exists (select 1 from public.tickets t where t.id = ticket_id and t.customer_id = auth.uid())
  );

drop policy if exists notifications_read_own on public.notifications;
create policy notifications_read_own on public.notifications for select to authenticated
  using (user_id = auth.uid());
drop policy if exists notifications_update_own on public.notifications;
create policy notifications_update_own on public.notifications for update to authenticated
  using (user_id = auth.uid()) with check (user_id = auth.uid());

drop policy if exists feedback_read_own_or_admin on public.feedback;
create policy feedback_read_own_or_admin on public.feedback for select to authenticated
  using (user_id = auth.uid() or public.has_app_role(array['admin', 'super_admin']));
drop policy if exists feedback_insert_completed_own on public.feedback;
create policy feedback_insert_completed_own on public.feedback for insert to authenticated
  with check (
    user_id = auth.uid()
    and exists (
      select 1 from public.tickets t
      where t.id = ticket_id and t.customer_id = auth.uid() and t.status = 'done'
    )
  );

revoke all on all tables in schema public from anon, authenticated;
grant select on public.services, public.branches to anon, authenticated;
grant select on public.profiles, public.user_roles, public.staff_branch_assignments,
  public.counters, public.staff_service_capabilities, public.tickets,
  public.ticket_predictions, public.queue_events, public.notifications,
  public.feedback to authenticated;
grant update (first_name, last_name, middle_name, phone, client_type, updated_at)
  on public.profiles to authenticated;
grant update ("read") on public.notifications to authenticated;
grant insert (ticket_id, user_id, counter_id, service_id, rating, comment)
  on public.feedback to authenticated;
grant insert, update, delete on public.user_roles to authenticated;
grant insert, update, delete on public.services, public.branches,
  public.staff_branch_assignments, public.counters,
  public.staff_service_capabilities to authenticated;

do $$
begin
  if exists (select 1 from pg_publication where pubname = 'supabase_realtime') then
    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'tickets'
    ) then
      alter publication supabase_realtime add table public.tickets;
    end if;
    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'counters'
    ) then
      alter publication supabase_realtime add table public.counters;
    end if;
    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime' and schemaname = 'public' and tablename = 'queue_events'
    ) then
      alter publication supabase_realtime add table public.queue_events;
    end if;
  end if;
end;
$$;

insert into public.branches (legacy_id, name, address, active)
values (1, 'Barangay Health Center', '', true)
on conflict (legacy_id) do nothing;

commit;
