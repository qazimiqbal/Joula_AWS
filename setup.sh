#!/bin/bash

# 🕌 Joula Project Setup Script
# This script helps you set up both frontend and backend

echo "🚀 Joula Project Setup"
echo "======================"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check Node.js
echo -e "${BLUE}Checking Node.js...${NC}"
if ! command -v node &> /dev/null; then
    echo -e "${YELLOW}⚠️  Node.js not found! Please install Node.js 16+${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Node.js found: $(node --version)${NC}"
echo ""

# Check MySQL
echo -e "${BLUE}Checking MySQL...${NC}"
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}⚠️  MySQL not found! Please install MySQL 8.0+${NC}"
else
    echo -e "${GREEN}✓ MySQL found${NC}"
fi
echo ""

# Setup Backend
echo -e "${BLUE}Setting up Backend...${NC}"
cd joula-backend || exit
echo "Installing dependencies..."
npm install

if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    cp .env.example .env
    echo -e "${YELLOW}📝 Please update .env with your MySQL credentials${NC}"
fi
echo -e "${GREEN}✓ Backend setup complete${NC}"
cd ..
echo ""

# Setup Frontend
echo -e "${BLUE}Setting up Frontend...${NC}"
cd joula-react || exit
echo "Installing dependencies..."
npm install

if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    cp .env.example .env
fi
echo -e "${GREEN}✓ Frontend setup complete${NC}"
cd ..
echo ""

echo -e "${GREEN}=====================================
✅ Setup complete!
=====================================
${NC}"

echo "Next steps:"
echo ""
echo "1. Update database credentials:"
echo "   Edit: joula-backend/.env"
echo ""
echo "2. Create MySQL database and tables:"
echo "   Run SQL from: joula-backend/README.md"
echo ""
echo "3. Start Backend (Terminal 1):"
echo "   cd joula-backend && npm run dev"
echo ""
echo "4. Start Frontend (Terminal 2):"
echo "   cd joula-react && npm run dev"
echo ""
echo "5. Open browser:"
echo "   http://localhost:3000"
echo ""
echo "📖 Documentation:"
echo "   - Frontend: joula-react/README.md"
echo "   - Backend: joula-backend/README.md"
echo "   - Migration: MODERNIZATION_GUIDE.md"
echo "   - Overview: PROJECT_OVERVIEW.md"
