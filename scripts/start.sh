#!/bin/sh
set -e

echo "🚀 Iniciando aplicação..."

# Executa migrações
./scripts/migrate.sh

# Inicia a aplicação
exec node dist/src/main

