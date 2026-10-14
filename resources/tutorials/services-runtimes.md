## Runtime مستقل
Node.js فقط برای build دارایی پنل در توسعه و CI لازم است؛ Production پنل به daemon Node وابسته نیست. پروژه Node runtime و package manager pin‌شده خودش را می‌خواهد.

## Nginx مشترک
سایت پروژه قالب و ownership مستقل دارد، ولی Nginx ممکن است بین پنل و پروژه‌ها مشترک باشد. حذف یا restart سراسری برای اصلاح یک پروژه پذیرفته نیست. candidate config باید validate و rollback داشته باشد.

## Redis
queue و cache پنل instance/policy مستقل دارند. queue با noeviction و persistence، cache با quota و eviction طراحی شده است. Redis پروژه نباید credential یا keyspace پنل را مصرف کند.

## مدیریت سرویس
systemd unit نام ثابت و allowlist‌شده می‌خواهد. process بی‌supervision پس از خروج terminal یا reboot قابل اتکا نیست. سرویس discovered بیرونی بدون رضایت adopt یا overwrite نمی‌شود. نصب runtimeها هنوز به executor و VM آزموده وابسته است.
