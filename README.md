# YoaPoint API – PHP + MVC + MySQL (PA4)

API REST de la tienda de tecnología **YoaPoint** (base de datos `yoapoint` de la PA3).
Curso: Programación Web Básica – ISIL · Autor: Aguirre Adrianzen Franz Yoans · 76542050@mail.isil.pe

## Arquitectura (MVC)
```
yoapoint_api/
├── public/index.php        # único punto de entrada (front controller)
├── bootstrap.php           # autoload, .env y manejo global de errores
├── config/                 # Database.php (PDO) y Env.php (lee .env)
├── core/                   # Router, Request, Response, Validator
├── app/
│   ├── Models/             # consultas SQL (PDO preparado)
│   ├── Controllers/        # validan, llaman al modelo y responden
│   └── Middleware/         # CORS
├── routes/api.php          # endpoints
├── database/yoapoint.sql   # script SQL + datos de prueba
├── postman/                # colección de Postman (80 pruebas)
├── evidencias/             # resultados de las pruebas
├── .env.example            # plantilla de variables de entorno
└── storage/logs/           # errores internos (no se muestran al cliente)
```

## Instalación en XAMPP
1. Copia la carpeta `yoapoint_api` en `C:\xampp\htdocs\`.
2. Inicia **Apache** y **MySQL** en el panel de XAMPP.
3. En phpMyAdmin (`http://localhost/phpmyadmin`) importa `database/yoapoint.sql`.
4. Copia `.env.example` como `.env` (por defecto: usuario `root`, sin contraseña, base `yoapoint`).
5. Prueba: `http://localhost/yoapoint_api/api` (debe mostrar "API YoaPoint en funcionamiento").

Requisitos: PHP 8.1+ con `pdo_mysql` (incluido en XAMPP). No usa Composer ni carpeta `vendor`.

## Endpoints
Cada recurso: `GET /api/{recurso}` · `GET /api/{recurso}/{id}` · `POST /api/{recurso}` · `PUT` · `PATCH` · `DELETE /api/{recurso}/{id}`

| Recurso | Tabla |
|---|---|
| `/api/categorias` | categorias |
| `/api/proveedores` | proveedores |
| `/api/productos` | productos (filtros `?id_categoria=`, `?q=`) |
| `/api/clientes` | clientes |
| `/api/usuarios` | usuarios (nunca devuelve la clave) |
| `/api/pedidos` | pedidos (el detalle por id incluye sus líneas) |
| `/api/detalle-pedido` | detalle_pedido (usa `sp_agregar_detalle_pedido`) |

Reportes: `GET /api/reportes/ventas-por-categoria` y `GET /api/reportes/pedidos-resumen?estado=PAGADO`

## Respuestas
```json
{ "success": true,  "message": "Registro creado correctamente", "data": { } }
{ "success": false, "message": "Los datos enviados no son válidos.", "errors": { "campo": "detalle" } }
```
Códigos: 200 OK · 201 Creado · 400 Datos inválidos · 404 No encontrado · 405 Método no permitido · 409 Conflicto (duplicado, relaciones, stock) · 500 Error interno.

## Pruebas
Importa `postman/YoaPoint_API.postman_collection.json` en Postman (variable `base_url` = `http://localhost/yoapoint_api`), importa antes el SQL y ejecuta la colección completa con **Runner**. Resultado esperado: 80 peticiones, 0 fallos.
