FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm install

COPY . .

EXPOSE ${PORT:-0}

CMD ["npm", "run", "dev"]
