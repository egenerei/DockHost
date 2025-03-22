```mermaid
    graph TD;
        A[Start] --> B[Ansible Provisions EC2 Instance]
        B --> C[EC2 Runs Docker with 2 Containers]
        C --> D[NGINX - Main Website]
        C --> E[MySQL - User Credentials Storage]
        D --> F[User Creates Account / Logs In]
        F -->|Authenticated?| G{Yes}
        F -->|Not Authenticated| H[Show Error & Retry] 
        G --> I[User Redirected to Admin Panel]
        I --> J[New Docker Strcture Created for User]
        J --> K[NGINX - User Web Server]
        J --> L[MySQL - User Database]
        J --> O[Other services]
        K --> M[User's Website Hosted]
        M --> N[End]
