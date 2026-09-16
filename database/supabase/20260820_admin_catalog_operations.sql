-- SmartQMS transactional Admin catalog operations.
-- Apply after 20260820_initial_schema.sql. Called only by protected PHP.

begin;

create or replace function public.admin_move_service(
  p_actor_legacy_id integer,
  p_service_legacy_id integer,
  p_direction text
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_actor uuid;
  v_current public.services;
  v_target public.services;
  v_current_order smallint;
begin
  if p_direction not in ('up', 'down') then
    raise exception 'Invalid service movement direction' using errcode = '22023';
  end if;
  select p.id into v_actor
  from public.profiles p
  join public.user_roles ur on ur.user_id = p.id
  where p.legacy_id = p_actor_legacy_id
    and p.active
    and ur.role in ('admin', 'super_admin')
  limit 1;
  if v_actor is null then
    raise exception 'Administrator role required' using errcode = '42501';
  end if;

  select * into v_current from public.services
  where legacy_id = p_service_legacy_id
  limit 1 for update;
  if not found then return jsonb_build_object('moved', false); end if;

  if p_direction = 'up' then
    select * into v_target from public.services
    where (display_order, id) < (v_current.display_order, v_current.id)
    order by display_order desc, id desc limit 1 for update;
  else
    select * into v_target from public.services
    where (display_order, id) > (v_current.display_order, v_current.id)
    order by display_order asc, id asc limit 1 for update;
  end if;
  if not found then return jsonb_build_object('moved', false); end if;

  v_current_order := v_current.display_order;
  update public.services set display_order = v_target.display_order, updated_at = now()
  where id = v_current.id;
  update public.services set display_order = v_current_order, updated_at = now()
  where id = v_target.id;
  return jsonb_build_object('moved', true);
end;
$$;

revoke all on function public.admin_move_service(integer, integer, text)
  from public, anon, authenticated;
grant execute on function public.admin_move_service(integer, integer, text)
  to service_role;

commit;
