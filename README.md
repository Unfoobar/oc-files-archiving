# ownCloud App: Files Archiving

This ownCloud app works like the windows network drive app, it registers a new archive storage "CDSTAR" in
the files_external app. The hook creates a new backend with the cdstar storage class, that extends the
OC\Files\Storage\Common class, like the other external storages do. As authentication mechanism I used the
wnd auth classes.

## Links

- [GWDG CDSTAR](https://info.gwdg.de/dokuwiki/doku.php?id=en:services:storage_services:gwdg_cdstar:start)