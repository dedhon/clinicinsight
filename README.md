# ClinicInsight

MVP Symfony para analitica de clinicas: subir Excel/CSV/JSON, detectar columnas sensibles, mapear campos, normalizar filas y mostrar KPIs con dashboard.

## Local

La app esta preparada en:

```text
C:\Proyectos\clinicinsight-app
```

Arranque local:

```powershell
cd C:\Proyectos\clinicinsight-app
php -S 127.0.0.1:8000 -t public
```

URL:

```text
http://127.0.0.1:8000/login
```

Usuario demo:

```text
admin@clinicinsight.local
admin123
```

## Base de datos

XAMPP/MariaDB:

```text
http://localhost/phpmyadmin
```

Base de datos:

```text
clinicinsight
```

## Variables locales

Configura secretos en `.env.local`. Ese archivo esta ignorado por Git.

```env
DATABASE_URL="mysql://clinic_user:clinic_password@127.0.0.1:3306/clinicinsight?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
OPENAI_API_KEY=""
OPENAI_MODEL="gpt-5.2"
UPLOADS_DIR="var/uploads"
```

## Comandos utiles

```powershell
php bin/console doctrine:migrations:migrate
php bin/console app:create-user admin@clinicinsight.local admin123 Admin
php bin/console cache:clear
```

## Privacidad

- No enviar filas completas a OpenAI.
- Para mapeo IA solo enviar cabeceras y pocos ejemplos truncados.
- Para insights IA solo enviar KPIs agregados.
- Ignorar columnas sensibles como nombre, DNI, telefono, email, direccion, diagnostico, tratamiento, notas, CIP e historia clinica.

