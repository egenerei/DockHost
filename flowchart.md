```mermaid
graph TD;
    A[inicio] --> B[Ansible aprovisiona instancia EC2]
    B --> C[EC2 ejecuta Docker con 2 contenedores]
    C --> D[Nginx - sitio web principal]
    D --> N["☁️ Internet ☁️"]
    C --> E[MySQL - almacenamiento de credenciales]
    D --> F[Usuario crea cuenta / inicia sesión]
    F -->|¿Autenticado?| G{Sí}
    F -->|No autenticado| H[Mostrar error & reintentar] 
    G --> I[Usuario redirigido al panel de administración]
    I --> J[Se crea nueva infraestructura Docker para el usuario]
    J --> K[Nginx - servidor web del usuario]
    J --> L[MySQL - base de datos del usuario]
    J --> O[Otros servicios]
    K --> M[Sitio web del usuario en línea]
    M -- "Servido por el contenedor Nginx principal a través de un subdominio" --> D


