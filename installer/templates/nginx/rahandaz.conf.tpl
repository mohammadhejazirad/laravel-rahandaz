# rahandaz-managed: control-plane
server {
    listen @@HTTP_PORT@@;
    listen [::]:@@HTTP_PORT@@;
    server_name @@SERVER_NAME@@ localhost;
    root @@PUBLIC_ROOT@@;
    index index.php;
    client_max_body_size @@CLIENT_MAX_BODY@@;
    server_tokens off;

    location = / {
        root @@WELCOME_ROOT@@;
        try_files /index.html =404;
    }

    location = /up {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME @@PUBLIC_ROOT@@/index.php;
        fastcgi_param REQUEST_URI /up;
        fastcgi_pass unix:@@FPM_SOCK@@;
    }

    location ^~ /rahandaz/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME @@PUBLIC_ROOT@@/index.php;
        fastcgi_pass unix:@@FPM_SOCK@@;
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ /\.(?!well-known/) {
        deny all;
    }

    location ~* \.(env|ini|log|sql|sqlite|key)$ {
        deny all;
    }
}
