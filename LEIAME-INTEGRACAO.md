# Integração back-end (núcleo)

```powershell
copy .env.example .env        # se ainda não existir
php artisan key:generate
# ajuste o .env: DB_CONNECTION=mysql, DB_DATABASE=irecycle, APP_LOCALE=pt_BR, APP_TIMEZONE=America/Sao_Paulo
php artisan migrate:fresh --seed
php artisan storage:link
php artisan schedule:work     # (outro terminal) fecha leilões vencidos a cada minuto
```

Contas de teste: admin@irecycle.com (admin123); vendedor@, empresa@, operador@, pendente@irecycle.com (123456).
Front: http://irecycle.test/frontend/index.html
