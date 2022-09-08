### Como funciona?
Solo clona el repositorio y ponlo dentro de la carpeta de tu apache para que puedas abrir el archivo envio.php y hacer las pruebas, tambien tienes que crear un archivo llamado security.php al mismo nivel que envio.php

### Que contiene security.php?
Son los datos de conexion a tu servidor de correos
```php
$HOST = 'servidor';
$SENDER_MAIL = 'correo remitente';
$SENDER_NAME = 'nombre remitente';
$SENDER_PASSWORD = 'contrasenia remitente';
```