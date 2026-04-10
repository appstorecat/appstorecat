FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE ${PORT:-0}

CMD ["sh", "-c", "uvicorn src.main:app --host 0.0.0.0 --port ${PORT} --reload"]
