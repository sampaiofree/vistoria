# Armazenamento e backup

## Discos usados pela aplicação

`config/filesystems.php` seleciona os discos de uploads por
`USER_IMAGES_STORAGE=local|r2`. O padrão do `.env.example` é `local`.

| Disco Laravel | Local | R2 |
|---|---|---|
| `inspection_photos` | `storage/app/private/inspection-photos` | `inspection-photos/` |
| `inspection_maps` | `storage/app/private/inspection-maps` | `inspection-maps/` |
| `branding_images` | `storage/app/private/branding` | `branding/` |

Os diretórios locais de fotos e mapas podem ser alterados por
`INSPECTION_PHOTOS_ROOT` e `INSPECTION_MAPS_ROOT`. No modo R2, os prefixos acima
ficam no bucket privado `R2_ASSETS_BUCKET`; são necessários
`R2_ASSETS_ACCESS_KEY_ID`, `R2_ASSETS_SECRET_ACCESS_KEY` e `R2_ENDPOINT`.
Sem esses valores, a configuração falha ao iniciar. Após trocar o modo, execute
`php artisan optimize:clear` e reinicie PHP-FPM e Horizon para recarregar a
configuração.

Logotipos, ícones, fotos e mapas enviados são lidos por rotas controladas pela
aplicação ou pelo gerador público de ícones PWA. O bucket não precisa de domínio
público, e os caminhos internos não são URLs de acesso direto. A troca entre
`local` e `r2` **não migra arquivos já existentes**: copie os objetos preservando
os caminhos relativos antes de apontar a instalação para o novo disco e valide
leitura, processamento, substituição e exclusão em homologação.

## Processamento

As fotos e mapas são processados por jobs na fila `images` do Horizon. O job de
mapa usa arquivo temporário local durante o trabalho, inclusive com R2. Portanto,
PHP-FPM, worker e diretório temporário precisam de espaço e permissão de escrita.
Consulte [Fotos](../arquitetura/fotos.md) e [Mapas](../arquitetura/mapas.md) para
os estados e as regras de acesso.

## Backup e restauração

O repositório **não contém** um job, comando ou script de backup automático do
MySQL, nem uma cópia independente dos assets. O cron Laravel registra apenas
`horizon:snapshot`. A instalação deve definir fora da aplicação a rotina, o
destino, a retenção e o teste de restauração do banco e dos objetos. Guarde também
`APP_KEY` e o `.env` protegido para restaurar uma instalação.

O bucket R2 de assets, quando configurado, é o armazenamento principal dos
uploads. Ele não fornece por si só uma cópia independente contra exclusão ou
dano dos objetos. Registre a data e o resultado de uma restauração de teste
antes de considerar o backup operacional.
