# Oracle Backend (Render)

PHP + SQLite API for **Sanjaya's Oracle — The Second Trial**.

This holds the answers and the flag **server-side** so they are not exposed in
the front-end page source.

## Endpoints

| Method | Path              | Body                                   | Returns |
|--------|-------------------|----------------------------------------|---------|
| GET    | `/api/health.php` | –                                      | `{status:"ok"}` |
| POST   | `/api/query.php`  | `{"q":"<input>"}`                      | `{rows:[]}` (always empty) |
| POST   | `/api/check.php`  | `{"type":"...","value":...}`           | `{ok, flag?, message?}` |

`type` is one of: `channel`, `length`, `name`, `vow`, `guess`.

## Deploy on Render

1. Push this folder to a Git repo (or use Render Blueprint with `render.yaml`).
2. New → Web Service → **Runtime: Docker**.
3. Render builds the `Dockerfile` and runs the PHP built-in server on `$PORT`.
4. Note the public URL, e.g. `https://oracle-backend.onrender.com`.

> Free Render instances sleep after inactivity; the first request may take
> ~30s to wake. The DB is created automatically on first use at `DB_PATH`.

## Local test

```bash
php -S 0.0.0.0:8080 -t public
curl -s -X POST localhost:8080/api/check.php -d '{"type":"guess","value":"barbarik"}'
```
