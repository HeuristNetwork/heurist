set @logged_in_user_id := 0;
set @suppress_update_trigger := 1;

start transaction;

call set_all_hhash();

update records set rec_simple_hash = simple_hash(rec_id);

commit;
