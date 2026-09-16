-- SmartQMS Phase 6: privileged branch and role administration.
-- Browser roles cannot call these functions; PHP invokes them with service_role.

create or replace function public.admin_save_branch(
  p_actor_legacy_id bigint,
  p_branch_id uuid,
  p_name text,
  p_address text,
  p_active boolean
) returns public.branches
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_actor uuid;
  v_branch public.branches;
begin
  select p.id into v_actor from public.profiles p
  where p.legacy_id = p_actor_legacy_id and p.active;
  if v_actor is null or not exists (
    select 1 from public.user_roles ur
    where ur.user_id = v_actor and ur.role in ('admin', 'super_admin')
  ) then raise exception 'Administrator permission required' using errcode = '42501'; end if;
  if nullif(btrim(p_name), '') is null then
    raise exception 'Branch name is required' using errcode = '22023';
  end if;
  if p_branch_id is null then
    insert into public.branches (name, address, active)
    values (btrim(p_name), nullif(btrim(p_address), ''), p_active)
    returning * into v_branch;
  else
    update public.branches set
      name = btrim(p_name), address = nullif(btrim(p_address), ''),
      active = p_active, updated_at = now()
    where id = p_branch_id returning * into v_branch;
    if v_branch.id is null then raise exception 'Branch not found' using errcode = 'P0002'; end if;
  end if;
  insert into public.queue_events (branch_id, event_type, actor_user_id, metadata)
  values (v_branch.id, case when p_branch_id is null then 'branch_created' else 'branch_updated' end,
          v_actor, jsonb_build_object('branch_name', v_branch.name, 'active', v_branch.active));
  return v_branch;
end;
$$;

revoke all on function public.admin_save_branch(bigint, uuid, text, text, boolean)
from public, anon, authenticated;
grant execute on function public.admin_save_branch(bigint, uuid, text, text, boolean) to service_role;

create or replace function public.super_admin_set_role(
  p_actor_legacy_id bigint,
  p_target_user_id uuid,
  p_role text,
  p_grant boolean
) returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_actor uuid;
  v_role text := lower(btrim(p_role));
begin
  select p.id into v_actor from public.profiles p
  where p.legacy_id = p_actor_legacy_id and p.active;
  if v_actor is null or not exists (
    select 1 from public.user_roles ur where ur.user_id = v_actor and ur.role = 'super_admin'
  ) then raise exception 'Super Administrator permission required' using errcode = '42501'; end if;
  if v_role not in ('customer', 'staff', 'admin', 'super_admin') then
    raise exception 'Unsupported role' using errcode = '22023';
  end if;
  if not exists (select 1 from public.profiles p where p.id = p_target_user_id) then
    raise exception 'Target profile not found' using errcode = 'P0002';
  end if;
  if not p_grant and p_target_user_id = v_actor and v_role = 'super_admin' then
    raise exception 'A Super Administrator cannot revoke their own elevated role' using errcode = '42501';
  end if;
  if p_grant then
    insert into public.user_roles (user_id, role) values (p_target_user_id, v_role)
    on conflict (user_id, role) do nothing;
  else
    delete from public.user_roles where user_id = p_target_user_id and role = v_role;
  end if;
  insert into public.queue_events (event_type, actor_user_id, metadata)
  values ('role_' || case when p_grant then 'granted' else 'revoked' end, v_actor,
          jsonb_build_object('target_user_id', p_target_user_id, 'role', v_role));
  return jsonb_build_object('user_id', p_target_user_id, 'role', v_role, 'granted', p_grant);
end;
$$;

revoke all on function public.super_admin_set_role(bigint, uuid, text, boolean)
from public, anon, authenticated;
grant execute on function public.super_admin_set_role(bigint, uuid, text, boolean) to service_role;

create or replace function public.server_finalize_customer_profile(p_user_id uuid)
returns public.profiles
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_profile public.profiles;
  v_legacy_id integer;
begin
  if not exists (select 1 from auth.users u where u.id = p_user_id) then
    raise exception 'Auth user not found' using errcode = 'P0002';
  end if;
  insert into public.profiles (id, first_name, last_name, phone)
  select u.id,
         coalesce(u.raw_user_meta_data ->> 'first_name', ''),
         coalesce(u.raw_user_meta_data ->> 'last_name', ''),
         nullif(u.raw_user_meta_data ->> 'phone', '')
  from auth.users u
  where u.id = p_user_id
  on conflict (id) do nothing;
  perform pg_advisory_xact_lock(hashtext('smartqms-profile-legacy-id'));
  select p.legacy_id into v_legacy_id from public.profiles p where p.id = p_user_id;
  if v_legacy_id is null then
    select coalesce(max(p.legacy_id), 0) + 1 into v_legacy_id from public.profiles p;
    update public.profiles set legacy_id = v_legacy_id, updated_at = now()
    where id = p_user_id;
  end if;
  insert into public.user_roles (user_id, role) values (p_user_id, 'customer')
  on conflict (user_id, role) do nothing;
  select * into v_profile from public.profiles p where p.id = p_user_id;
  return v_profile;
end;
$$;

revoke all on function public.server_finalize_customer_profile(uuid)
from public, anon, authenticated;
grant execute on function public.server_finalize_customer_profile(uuid) to service_role;
