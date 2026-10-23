# Cyla
Site d'hébergement de fichiers simple, réduit au strict nécessaire.

![image](https://concepts.esenjin.xyz/cyla/fichiers/6787d3fe886e4_1736954878.png)

## Prérequis

- **PHP 8.5 ou supérieur** (obligatoire depuis la version 3.3.0)
- Extension `session` (activée par défaut)
- Extension `curl` (uniquement pour la connexion Vestikan)
- Apache avec les fichiers `.htaccess` autorisés (`AllowOverride`), ou configuration équivalente

Au chargement, Cyla vérifie la version de PHP et affiche un message explicite
(erreur HTTP 500) si le serveur tourne sur une version antérieure à 8.5.
