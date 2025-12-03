FROM node:20-alpine AS builder

# Install pnpm
RUN corepack enable && corepack prepare pnpm@10.24.0 --activate

WORKDIR /app

# Copy package files first (better Docker cache)
COPY package.json pnpm-lock.yaml ./

# Install ALL dependencies (needed for build)
RUN pnpm install --frozen-lockfile

# Copy Prisma schema
COPY prisma ./prisma
COPY prisma.config.ts ./

# Generate Prisma Client (only once, in builder)
RUN npx prisma generate

# Copy source code (this changes most frequently)
COPY . .

# Build the application
RUN pnpm build

# Production stage
FROM node:20-alpine

# Install pnpm
RUN corepack enable && corepack prepare pnpm@10.24.0 --activate

WORKDIR /app

# Copy package files
COPY package.json pnpm-lock.yaml ./

# Install only production dependencies
RUN pnpm install --prod --frozen-lockfile

# Install prisma CLI as dev dependency (needed for migrations)
RUN pnpm add -D prisma@7.0.1 @prisma/config@7.0.1

# Copy Prisma schema and config
COPY prisma ./prisma
COPY prisma.config.ts ./

# Generate Prisma Client in production
RUN npx prisma generate

# Install pg_isready for health check
RUN apk add --no-cache postgresql-client

# Copy migration script
COPY scripts/migrate.sh ./scripts/migrate.sh
RUN chmod +x ./scripts/migrate.sh

# Copy built application from builder stage
COPY --from=builder /app/dist ./dist

# Expose port
EXPOSE 3000

# Start script that runs migrations then starts the app
COPY scripts/start.sh ./scripts/start.sh
RUN chmod +x ./scripts/start.sh

# Start the application
CMD ["./scripts/start.sh"]