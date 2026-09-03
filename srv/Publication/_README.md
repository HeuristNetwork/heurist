# Publication workflow

`PublicationService.php` stores module-neutral publication documents in the
database filestore's `generated-pubs` directory. A document identifies its
module through `type` (`map`, `data`, `timeline`, `graph`, or `crosstabs`) and
contains the shared `options`, `config`, and `state` envelope.

The public HTML response is produced by `Controller/PublicationController.php`.
It selects the corresponding `hclient/bundles/heurist-{type}` assets and exposes
the launch envelope as `window.heuristModuleBootstrap`.
