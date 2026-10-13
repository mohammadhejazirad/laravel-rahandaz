[Unit]
Description=Run the Rahandaz scheduler every minute

[Timer]
OnCalendar=minutely
AccuracySec=10s
Persistent=true
Unit=rahandaz-scheduler.service

[Install]
WantedBy=timers.target
