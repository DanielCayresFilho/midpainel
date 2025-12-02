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

# Install prisma CLI temporarily as dev dependency to generate client
RUN pnpm add -D prisma@7.0.1 @prisma/config@7.0.1

# Copy Prisma schema and config
COPY prisma ./prisma
COPY prisma.config.ts ./

# Generate Prisma Client in production
RUN npx prisma generate

# Remove prisma CLI to reduce image size (optional)
RUN pnpm remove prisma @prisma/config

# Copy built application from builder stage
COPY --from=builder /app/dist ./dist

# Expose port
EXPOSE 3000

# Start the application
CMD ["node", "dist/src/main"]