FROM node:22-alpine AS portal
WORKDIR /portal
COPY portal/package.json portal/package-lock.json ./
RUN npm ci
COPY portal/ ./
RUN npm run build

FROM nginx:1.27-alpine
COPY infra/nginx/lab.conf /etc/nginx/conf.d/default.conf
COPY --from=portal /portal/dist /usr/share/nginx/html
COPY api/public /var/www/api/public
