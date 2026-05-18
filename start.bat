@echo off

echo iniciando backend

cd backend
start cmd /k "cd /d %~dp0backend && php artisan serve"

echo iniciando frontend

cd ../frontend
start cmd /k "cd /d %~dp0frontend && npm run dev"

echo servidores iniciados!

timeout /t 3

start http://localhost:5173