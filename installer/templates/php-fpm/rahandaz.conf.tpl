[rahandaz]
user = rahandaz
group = rahandaz
listen = @@FPM_SOCK@@
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
php_admin_value[upload_max_filesize] = @@UPLOAD_MAX@@
php_admin_value[post_max_size] = @@UPLOAD_MAX@@
php_admin_flag[expose_php] = off
clear_env = no
catch_workers_output = yes
