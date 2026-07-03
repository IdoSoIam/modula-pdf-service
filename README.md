# Modula PDF Service

Service PHP pour la generation PDF de Modula CMS avec `tc-lib-pdf` uniquement.
Le service est prevu pour etre appele en serveur a serveur par `modula-cms`.

## Prerequis

- PHP 8.2+
- extension PHP `gd` activee
- `composer install` execute
- Apache avec `mod_rewrite` ou un front controller equivalent

## Configuration

Copier `.env.example` vers `.env` puis definir au minimum :

```env
MODULA_PDF_API_KEY="une-cle-longue-et-secrete"
MODULA_PDF_ENABLE_PLAYGROUND=0
```

- `MODULA_PDF_API_KEY`
  - cle attendue sur le header HTTP `X-Modula-PDF-Key`
  - a reporter cote `modula-cms` dans `CMS_PDF_SERVICE_API_KEY`
- `MODULA_PDF_ENABLE_PLAYGROUND`
  - `0` en ligne
  - `1` uniquement si vous voulez garder l interface de test sur `/`

## Lancer en local

```powershell
& 'C:\xampp\php\php.exe' D:\Works\composer.phar dump-autoload --working-dir=D:\Works\modula-pdf-service
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8092 -t D:\Works\modula-pdf-service\public
```

Ou avec le script fourni:

```powershell
powershell -ExecutionPolicy Bypass -File D:\Works\modula-pdf-service\scripts\start-local.ps1
```

Ensuite ouvrir:

- `http://127.0.0.1:8092/`
- `http://127.0.0.1:8092/health`

## Endpoints

- `GET /`
  - interface de test locale avec payload JSON editable
- `GET /health`
  - verifie que le service demarre et expose la police active
- `POST /api/render`
  - genere un PDF depuis un payload JSON
  - protege par `X-Modula-PDF-Key` si `MODULA_PDF_API_KEY` est defini

## Deploiement web classique

### 1. Fichiers a publier

Publier tout le projet, y compris :

- `public/`
- `src/`
- `vendor/`
- `storage/fonts/custom/`
- `.htaccess`

Ne pas publier :

- `.env.example`
- les logs et PDF de test dans `storage/`

### 2. Pointage web

Le `DocumentRoot` ou le sous-dossier web doit pointer vers :

```txt
public/
```

Le `.htaccess` fourni redirige `/health` et `/api/render` vers `public/index.php`.

### 3. Variables cote Modula CMS

Dans l instance `modula-cms` qui consomme ce service :

```env
CMS_PDF_SERVICE_URL="https://votre-service-pdf.exemple"
CMS_PDF_SERVICE_API_KEY="la-meme-cle-que-MODULA_PDF_API_KEY"
```

## Notes techniques

- Si `storage/fonts/custom/` contient deja des polices converties, elles sont reutilisees telles quelles.
- Sous Windows local, le service peut encore importer Arial depuis `C:\Windows\Fonts` si necessaire.
- Sur un hebergement Linux, il n y a plus de dependance obligatoire a `C:\Windows\Fonts`.
- Le service utilise `tc-lib-pdf` uniquement.
- Le service est exploitable en ligne tel quel si le front web pointe sur `public/` et que la cle API est configuree.
