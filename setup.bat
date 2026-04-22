@echo off
REM 🕌 Joula Project Setup Script for Windows

echo 🚀 Joula Project Setup
echo =====================

REM Check Node.js
echo.
echo Checking Node.js...
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ⚠️  Node.js not found! Please install Node.js 16+
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node --version') do set NODE_VERSION=%%i
echo ✓ Node.js found: %NODE_VERSION%

REM Setup Backend
echo.
echo Setting up Backend...
cd joula-backend
echo Installing dependencies...
call npm install

if not exist .env (
    echo Creating .env file...
    copy .env.example .env
    echo 📝 Please update .env with your MySQL credentials
)
echo ✓ Backend setup complete
cd ..

REM Setup Frontend
echo.
echo Setting up Frontend...
cd joula-react
echo Installing dependencies...
call npm install

if not exist .env (
    echo Creating .env file...
    copy .env.example .env
)
echo ✓ Frontend setup complete
cd ..

echo.
echo =====================================
echo ✅ Setup complete!
echo =====================================

echo.
echo Next steps:
echo.
echo 1. Update database credentials:
echo    Edit: joula-backend\.env
echo.
echo 2. Create MySQL database and tables:
echo    Run SQL from: joula-backend\README.md
echo.
echo 3. Start Backend (Command Prompt 1):
echo    cd joula-backend && npm run dev
echo.
echo 4. Start Frontend (Command Prompt 2):
echo    cd joula-react && npm run dev
echo.
echo 5. Open browser:
echo    http://localhost:3000
echo.
echo Documentation:
echo    - Frontend: joula-react\README.md
echo    - Backend: joula-backend\README.md
echo    - Migration: MODERNIZATION_GUIDE.md
echo    - Overview: PROJECT_OVERVIEW.md
echo.
pause
