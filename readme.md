# Lista de Tarefas com Imagens

Aplicação de tarefas com upload de imagens, drag and drop e modo de seleção.

## Tecnologias

- **Frontend:** Vue 3 + Vuedraggable + Axios
- **Backend:** Laravel
- **Banco:** SQLite

## Requisitos
- **PHP (com extensões: `curl`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, `fileinfo`)** 
- **Composer** 
- **Node.Js e NPM** 
- **Git**

## Passos:
## 1 - Clonar o repositório:

```bash
git clone <url-do-repositorio>
cd projeto_laravel_vue
```

## 2 - Configuração do backend
```bash
cd backend
composer install
copy .env.example .env
# ou no Linux/Mac: cp .env.example .env
php artisan key:generate
type nul > database\database.sqlite   (Windows)
# ou no Linux/Mac: touch database/database.sqlite
```

## 3 - Configuração do backend
```bash
cd frontend
npm install
```

## 4 - Execução
- Executar o **start.bat**.