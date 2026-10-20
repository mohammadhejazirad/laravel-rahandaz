; rahandaz-managed: panel-pool-v1
[rahandaz]
user = rahandaz-svc
group = rahandaz-svc
listen = @@FPM_SOCK@@
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
; No idle workers while the panel is closed. The cap stays small on a 2 GiB host.
pm = ondemand
pm.max_children = 3
pm.process_idle_timeout = 10s
pm.max_requests = 500
php_admin_value[memory_limit] = 192M
php_admin_value[upload_max_filesize] = @@UPLOAD_MAX@@
php_admin_value[post_max_size] = @@UPLOAD_MAX@@
php_admin_flag[expose_php] = off
clear_env = no
catch_workers_output = yes
