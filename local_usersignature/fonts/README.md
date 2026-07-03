# Fontes de assinatura

As fontes são servidas pelo endpoint `font.php`, que procura os arquivos em
DUAS localizações, nesta ordem:

1. `local/usersignature/fonts/` (esta pasta, empacotada com o plugin)
2. `moodledata/fonts/` (`$CFG->dataroot/fonts/` — fontes instaladas pelo
   administrador no servidor)

Nomes de arquivo esperados (maiúsculas/minúsculas aceitas; `.woff2` é
preferido, depois `.woff`, `.ttf` e `.otf`):

- `Autography` — fonte padrão
- `Caveat`
- `Sacramento`
- `Aerotis`

Exemplo: `moodledata/fonts/Autography.ttf` ou `fonts/autography.woff2`.

## Onde obter

- **Caveat** e **Sacramento** são gratuitas (licença OFL):
  https://fonts.google.com/specimen/Caveat e
  https://fonts.google.com/specimen/Sacramento
- **Autography** e **Aerotis** são fontes de terceiros (ex.: dafont/Creative
  Fabrica); verifique a licença de uso antes de implantar em produção.
