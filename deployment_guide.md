# Guía de Despliegue en VPS para psicobloom.com

Esta guía detalla los pasos para apuntar tu nuevo dominio `psicobloom.com` a tu VPS y configurar el servidor web (Nginx o Apache) para alojar la web de Laura de manera aislada y segura, **sin interferir en lo absoluto con tu sistema ERP de Ventux**.

---

## Paso 1: Configurar los Registros DNS (Apuntar Dominio)
En el registrador donde compraste el dominio (Hostinger, DonWeb, GoDaddy, etc.), ve a la sección de **Zona DNS** de `psicobloom.com` y crea o edita los siguientes registros:

1. **Registro A (Dominio principal):**
   - **Nombre/Host:** `@` o déjalo vacío.
   - **Valor/Apunta a:** `IP_DE_TU_VPS` (Ej: `198.51.100.42`)
   - **TTL:** Por defecto (o `14400`).
2. **Registro CNAME o A (Subdominio www):**
   - **Nombre/Host:** `www`
   - **Valor/Apunta a:** `psicobloom.com` (o la `IP_DE_TU_VPS` si es un registro A).

*Nota: La propagación DNS suele tardar de unos minutos a un par de horas.*

---

## Paso 2: Crear el Directorio Web en la VPS
Conéctate por SSH a tu VPS y crea la carpeta donde se guardarán los archivos de Laura. Es una práctica estándar crearlo dentro de `/var/www/`:

```bash
sudo mkdir -p /var/www/psicobloom
```

### Subir los Archivos:
Copia todos los archivos de tu carpeta local `public/` (los HTML, PHP, JSON y las imágenes) dentro de esa nueva carpeta `/var/www/psicobloom`.

### Asignar Permisos Correctos:
Dado que la administración web (`api.php`) necesita escribir en `servicios.json`, el servidor web debe ser dueño de los archivos para evitar errores de permisos:

```bash
# Cambia el propietario al usuario del servidor web (comúnmente www-data)
sudo chown -R www-data:www-data /var/www/psicobloom

# Configura los permisos de escritura correctos
sudo chmod -R 755 /var/www/psicobloom
sudo chmod 664 /var/www/psicobloom/servicios.json
```

---

## Paso 3: Configurar el Servidor Web (Virtual Host)
Para que tu VPS sepa dirigir `psicobloom.com` a la carpeta correcta sin afectar a Ventux ERP, debes agregar un archivo de configuración separado.

### OPCIÓN A: Si usas Nginx (Recomendado)
Crea un archivo de configuración exclusivo para Psicobloom en los sitios disponibles de Nginx:

```bash
sudo nano /etc/nginx/sites-available/psicobloom
```

Pega la siguiente configuración:

```nginx
server {
    listen 80;
    server_name psicobloom.com www.psicobloom.com;
    root /var/www/psicobloom;
    index index.html index.php;

    # Enrutamiento de URLs Amigables (Pretty URLs sin .html)
    location / {
        try_files $uri $uri/ $uri.html =404;
    }

    # Configuración para ejecutar PHP (api.php)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock; # Verifica tu versión de PHP-FPM si da error
    }

    # Bloqueo de seguridad para evitar accesos a archivos ocultos
    location ~ /\. {
        deny all;
    }
}
```

Habilita el sitio y reinicia Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/psicobloom /etc/nginx/sites-enabled/
sudo nginx -t # Verifica que la sintaxis esté perfecta
sudo systemctl reload nginx
```

---

### OPCIÓN B: Si usas Apache
Crea un archivo de configuración en Apache:

```bash
sudo nano /etc/apache2/sites-available/psicobloom.conf
```

Pega la siguiente configuración:

```apache
<VirtualHost *:80>
    ServerName psicobloom.com
    ServerAlias www.psicobloom.com
    ServerAdmin admin@psicobloom.com
    DocumentRoot /var/www/psicobloom

    <Directory /var/www/psicobloom>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/psicobloom_error.log
    CustomLog ${APACHE_LOG_DIR}/psicobloom_access.log combined
</VirtualHost>
```

Habilita el sitio y reinicia Apache:

```bash
sudo a2ensite psicobloom.conf
sudo apache2ctl configtest # Verifica que no haya errores
sudo systemctl reload apache2
```

---

## Paso 4: Instalar Certificado SSL (HTTPS Seguro y Gratuito)
Es fundamental para la confianza de los clientes (y para las APIs) que el sitio tenga candado HTTPS. Puedes usar Certbot con Let's Encrypt de forma automática sin arriesgar nada:

### Para Nginx:
```bash
sudo certbot --nginx -d psicobloom.com -d www.psicobloom.com
```

### Para Apache:
```bash
sudo certbot --apache -d psicobloom.com -d www.psicobloom.com
```

Certbot se encargará de modificar **únicamente** la configuración de `psicobloom` añadiendo los certificados SSL y creando redirecciones automáticas de HTTP a HTTPS. Tu ERP Ventux seguirá operando en sus propios puertos o dominios de manera intacta.
