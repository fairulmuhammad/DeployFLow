#!/bin/sh
# Creates the DeployFlow database roles on first initialization.
# Runs only when the postgres data volume is empty.
#
#   deployflow_app      DML only (the application and queue workers)
#   deployflow_migrate  DDL (migrations, run as a separate step)
#   deployflow_readonly reporting/ops reads
#
# Passwords come from compose environment variables and default to local-only
# values. They must never be reused outside local development.

set -eu

app_user="${DEPLOYFLOW_APP_USER:-deployflow_app}"
app_password="${DEPLOYFLOW_APP_PASSWORD:-deployflow}"
migrate_user="${DEPLOYFLOW_MIGRATE_USER:-deployflow_migrate}"
migrate_password="${DEPLOYFLOW_MIGRATE_PASSWORD:-deployflow}"
readonly_user="${DEPLOYFLOW_READONLY_USER:-deployflow_readonly}"
readonly_password="${DEPLOYFLOW_READONLY_PASSWORD:-deployflow}"
database="${POSTGRES_DB:-deployflow}"

psql --set=ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$database" \
    --set=db="$database" \
    --set=app_user="$app_user" \
    --set=app_password="$app_password" \
    --set=migrate_user="$migrate_user" \
    --set=migrate_password="$migrate_password" \
    --set=readonly_user="$readonly_user" \
    --set=readonly_password="$readonly_password" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'app_user', :'app_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user')\gexec

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'migrate_user', :'migrate_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'migrate_user')\gexec

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'readonly_user', :'readonly_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'readonly_user')\gexec

ALTER ROLE :"app_user" NOSUPERUSER NOCREATEDB NOCREATEROLE;
ALTER ROLE :"migrate_user" NOSUPERUSER NOCREATEDB NOCREATEROLE;
ALTER ROLE :"readonly_user" NOSUPERUSER NOCREATEDB NOCREATEROLE;

-- The local stack runs migrations as deployflow_app (see docker-compose.yml),
-- so it needs schema CREATE. In production, grant CREATE only to the migrate
-- role (DEPLOYFLOW_MIGRATE_USER) and revoke it from the app role.
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db" TO :"app_user", :"migrate_user", :"readonly_user";

GRANT USAGE ON SCHEMA public TO :"app_user", :"migrate_user", :"readonly_user";
GRANT CREATE ON SCHEMA public TO :"app_user", :"migrate_user";

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app_user";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app_user";
GRANT SELECT ON ALL TABLES IN SCHEMA public TO :"readonly_user";

ALTER DEFAULT PRIVILEGES FOR ROLE :"migrate_user" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES FOR ROLE :"migrate_user" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";
ALTER DEFAULT PRIVILEGES FOR ROLE :"migrate_user" IN SCHEMA public
    GRANT SELECT ON TABLES TO :"readonly_user";
SQL
