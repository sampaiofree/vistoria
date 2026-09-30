# 14 — Cloudflare R2 para fotos/mapas e backup do MySQL

## 1. Objetivo

Migrar o armazenamento persistente de **fotografias** e **mapas de inspeção** da VPS para o **Cloudflare R2**, mantendo os arquivos privados e preservando a API de filesystem do Laravel.

Também implementar **backup automático do MySQL para um bucket R2 separado**, de forma que a perda total da VPS não implique perda das imagens, mapas ou banco de dados.

Este documento não altera a regra atual de processamento das imagens. O fluxo continua sendo:

```text
upload
→ processamento assíncrono pelo Horizon/Imagick
→ geração dos derivados
→ gravação no storage definitivo
```

Para fotografias, continuam existindo `optimized.webp` e `thumbnail.webp`. Para mapas, continuam existindo o fundo processado e a miniatura.

---

## 2. Arquitetura alvo

```text
                         CLOUDFLARE R2

                 ┌─────────────────────────┐
                 │ bucket: vistoria-assets │
                 │                         │
Laravel / VPS ───┤ inspection-photos/      │
                 │ inspection-maps/        │
                 └─────────────────────────┘

                 ┌──────────────────────────┐
Backup MySQL ────┤ bucket: vistoria-backups│
                 │ mysql/                   │
                 └──────────────────────────┘
```

### Decisão importante

Usar **dois buckets diferentes**:

1. `vistoria-assets`: fotos e mapas utilizados pela aplicação;
2. `vistoria-backups`: backups do MySQL.

Também devem existir **credenciais R2 diferentes** para a aplicação e para o processo de backup.

A aplicação não deve possuir permissão sobre o bucket de backups. Isso reduz o risco de um erro ou comprometimento da aplicação apagar também os backups.

---

## 3. Situação atual que deve ser preservada

O sistema atualmente possui discos privados separados para:

```text
equipment_documents
inspection_photos
inspection_maps
```

Nesta etapa:

```text
inspection_photos → migrar para R2
inspection_maps  → migrar para R2
equipment_documents → permanecer como está
```

Documentos poderão ser migrados posteriormente, sem fazer parte desta implementação.

Os arquivos de fotos e mapas devem continuar privados. Não criar bucket público e não depender de URL pública direta do R2.

O acesso da aplicação deve continuar passando pelas regras de tenant, Policies e Controllers já existentes.

---

# PARTE A — R2 PARA FOTOS E MAPAS

## 4. Criar o bucket de assets

No Cloudflare R2 criar:

```text
Bucket: vistoria-assets
```

O bucket deve permanecer **privado**.

Criar uma credencial/API Token R2 limitada a esse bucket com as permissões necessárias de leitura e escrita de objetos.

Guardar:

```text
Account ID
Access Key ID
Secret Access Key
Endpoint S3
Bucket
```

Endpoint esperado:

```text
https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

Nunca versionar essas credenciais no Git.

---

## 5. Dependências Laravel

O Laravel 13 suporta storages compatíveis com S3, incluindo Cloudflare R2.

Adicionar, se ainda não existir:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```

Para manter discos separados utilizando o mesmo bucket com prefixos distintos, utilizar scoped disks:

```bash
composer require league/flysystem-path-prefixing "^3.0"
```

---

## 6. Variáveis de ambiente

Adicionar ao `.env` de produção:

```dotenv
R2_ASSETS_ACCESS_KEY_ID=
R2_ASSETS_SECRET_ACCESS_KEY=
R2_ASSETS_BUCKET=vistoria-assets
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_REGION=auto
```

Não utilizar as mesmas credenciais do backup do MySQL.

---

## 7. Configuração do filesystem

Em `config/filesystems.php`, criar um disco base para o R2:

```php
'r2_assets' => [
    'driver' => 's3',
    'key' => env('R2_ASSETS_ACCESS_KEY_ID'),
    'secret' => env('R2_ASSETS_SECRET_ACCESS_KEY'),
    'region' => env('R2_REGION', 'auto'),
    'bucket' => env('R2_ASSETS_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => false,
    'throw' => true,
],
```

Criar discos com prefixos separados:

```php
'inspection_photos_r2' => [
    'driver' => 'scoped',
    'disk' => 'r2_assets',
    'prefix' => 'inspection-photos',
],

'inspection_maps_r2' => [
    'driver' => 'scoped',
    'disk' => 'r2_assets',
    'prefix' => 'inspection-maps',
],
```

### Regra de arquitetura

Não espalhar `r2_assets` ou nomes do provedor pelo código de domínio.

A aplicação deve obter o disco por configuração. Exemplo:

```dotenv
INSPECTION_PHOTOS_DISK=inspection_photos_r2
INSPECTION_MAPS_DISK=inspection_maps_r2
```

Em desenvolvimento e testes poderá continuar utilizando discos locais/fake.

Objetivo:

```text
regra de negócio → filesystem Laravel → R2
```

O código de domínio não deve saber se o arquivo está no R2, S3 ou disco local.

---

## 8. Caminhos dos objetos

Persistir no banco apenas o **caminho relativo**, nunca a URL completa do Cloudflare.

Exemplo:

```text
organizations/7/inspections/55/assessments/10/photos/123/optimized.webp
```

O objeto real ficará no bucket como:

```text
inspection-photos/
└── organizations/
    └── 7/
        └── inspections/
            └── 55/
                └── assessments/
                    └── 10/
                        └── photos/
                            └── 123/
                                ├── optimized.webp
                                └── thumbnail.webp
```

Para mapas:

```text
inspection-maps/
└── organizations/...
```

### Regra

Se os paths já armazenados atualmente no banco forem relativos ao disco, **preservá-los**.

Não migrar registros para URLs como:

```text
https://....r2.cloudflarestorage.com/...
```

Isso criaria dependência do provedor e dificultaria futuras migrações.

---

## 9. Processamento das imagens

Não alterar as regras técnicas atuais apenas por causa da migração de storage.

### Fotografias

Manter:

```text
optimized.webp
thumbnail.webp
```

O original temporário continua podendo ser removido após a geração bem-sucedida dos derivados.

### Mapas

Manter o processamento atual e os derivados utilizados pelo editor e relatório.

### Atenção

Imagick precisa de arquivo local para diversas operações. Portanto, o Job pode trabalhar com arquivo temporário local durante o processamento e somente depois enviar o resultado final para o R2.

Fluxo recomendado:

```text
upload
↓
arquivo temporário local
↓
Imagick
↓
optimized/thumbnail ou mapa processado
↓
R2
↓
confirmar gravação
↓
remover temporários locais
↓
status ready
```

Nunca definir o registro como `ready` antes de confirmar que os objetos finais existem no storage.

---

## 10. Privacidade e entrega

Nesta primeira implementação, manter o modelo atual:

```text
Navegador
   ↓
Controller Laravel
   ↓
Policy / Tenant
   ↓
Storage R2
```

Não tornar o bucket público.

Não alterar para URL pública direta sem revisar previamente as regras de autorização.

Uma otimização futura poderá utilizar URL temporária/presigned URL, mas não faz parte desta implementação.

---

## 11. Migração dos arquivos existentes

A mudança do filesystem não pode fazer as imagens antigas desaparecerem.

Ordem recomendada:

```text
1. configurar R2
2. testar upload/leitura/exclusão em homologação
3. copiar os arquivos existentes para R2
4. conferir arquivos migrados
5. somente depois trocar produção para R2
6. manter a cópia local antiga por período de segurança
7. remover a cópia local apenas após validação
```

A cópia deve preservar exatamente os caminhos relativos já utilizados no banco.

Pode ser utilizado `rclone`, AWS CLI compatível com R2 ou um comando Artisan específico de migração.

### Não fazer

Não executar primeiro:

```text
apagar local → mudar configuração → tentar migrar
```

A migração deve ser de **cópia**, não de movimentação, até a validação final.

---

## 12. Validações mínimas do R2

Antes de considerar a migração concluída, testar:

- upload de foto;
- processamento da foto pelo Horizon;
- geração de `optimized.webp`;
- geração de `thumbnail.webp`;
- visualização autorizada;
- bloqueio de acesso entre tenants;
- exclusão/substituição de fotografia;
- upload e processamento de mapa;
- editor de localização;
- geração do relatório utilizando imagens do R2;
- reinicialização do Horizon;
- indisponibilidade temporária do R2;
- falha de gravação sem marcar a imagem como `ready`.

Também executar a suíte automatizada existente.

---

# PARTE B — BACKUP DO MYSQL NO R2

## 13. Bucket separado de backup

Criar:

```text
Bucket: vistoria-backups
```

Esse bucket não deve ser acessível pelas credenciais utilizadas pela aplicação Laravel.

Criar uma segunda credencial R2 exclusiva para backup.

Exemplo de organização:

```text
vistoria-backups/
└── mysql/
    ├── 2026/
    │   ├── 09/
    │   │   ├── vistoria-20260929-030000.sql.gz
    │   │   └── ...
```

---

## 14. Credenciais do backup

Guardar em arquivo separado e protegido no servidor, por exemplo:

```text
/etc/vistoria/backup.env
```

Permissões:

```bash
sudo chown root:root /etc/vistoria/backup.env
sudo chmod 600 /etc/vistoria/backup.env
```

Exemplo conceitual:

```dotenv
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
R2_BACKUP_BUCKET=vistoria-backups
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

Não colocar essas credenciais no repositório.

---

## 15. Backup automático

Criar um script de sistema, por exemplo:

```text
/usr/local/bin/vistoria-mysql-backup.sh
```

Fluxo obrigatório:

```text
1. criar dump consistente do MySQL
2. compactar com gzip
3. validar que o arquivo foi criado e não está vazio
4. enviar ao R2
5. confirmar sucesso do upload
6. remover o arquivo temporário local
7. retornar erro se qualquer etapa falhar
```

Comando base para dump:

```bash
mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  vistoria | gzip > "$BACKUP_FILE"
```

As credenciais do MySQL não devem aparecer no comando. Utilizar arquivo de opções protegido ou outra forma segura já adotada pela infraestrutura.

Para envio ao R2 pode ser utilizado AWS CLI ou `rclone`.

Exemplo com AWS CLI:

```bash
aws s3 cp \
  "$BACKUP_FILE" \
  "s3://$R2_BACKUP_BUCKET/mysql/$YEAR/$MONTH/$FILENAME" \
  --endpoint-url "$R2_ENDPOINT"
```

O script deve utilizar `set -euo pipefail` ou tratamento equivalente para não reportar sucesso após falha parcial.

---

## 16. Frequência

Configuração inicial recomendada:

```text
Backup MySQL: diário
Horário sugerido: 03:00
```

Cron de sistema:

```cron
0 3 * * * /usr/local/bin/vistoria-mysql-backup.sh >> /var/log/vistoria-mysql-backup.log 2>&1
```

Esse backup deve permanecer separado do `php artisan schedule:run` da aplicação.

O scheduler Laravel atual deve continuar responsável apenas pelas tarefas da aplicação.

---

## 17. Retenção

Não permitir crescimento infinito do bucket de backups.

Política inicial sugerida:

```text
backups diários: 30 dias
backups mensais: 12 meses
```

A retenção poderá ser ajustada quando houver dados reais de volume e necessidade contratual.

A política de exclusão deve atuar somente sobre `vistoria-backups` e nunca sobre `vistoria-assets`.

---

## 18. Restauração do banco

Ter backup sem testar restauração não é suficiente.

Procedimento básico:

```text
1. selecionar um backup no R2
2. baixar o .sql.gz
3. criar banco de destino limpo
4. restaurar o dump
5. executar verificações da aplicação
6. confirmar integridade das relações com os objetos do R2
```

Exemplo:

```bash
gunzip -c vistoria-AAAAmmdd-HHMMSS.sql.gz | mysql vistoria
```

Nunca testar uma restauração destrutiva diretamente sobre produção sem procedimento específico.

Realizar periodicamente uma restauração em ambiente isolado.

---

# PARTE C — RECUPERAÇÃO DE DESASTRE

## 19. Cenário esperado após a implementação

Se a VPS for perdida completamente, os dados necessários para reconstrução serão:

```text
Git / código da aplicação
+
segredos e .env
+
backup MySQL no R2
+
assets permanentes no R2
```

Fluxo de recuperação:

```text
nova VPS
↓
instalar dependências
↓
obter código aprovado
↓
configurar .env
↓
restaurar MySQL do vistoria-backups
↓
configurar credenciais do vistoria-assets
↓
subir Laravel / Nginx / Redis / Horizon
↓
validar aplicação
```

Fotos e mapas **não precisam ser copiados de volta para o disco da VPS**. A nova instalação volta a acessá-los diretamente no R2.

---

## 20. O R2 não substitui completamente backup dos assets

O bucket `vistoria-assets` será o armazenamento principal das fotos e mapas.

Portanto:

```text
perda da VPS → arquivos continuam seguros no R2
```

porém:

```text
bug/apagamento no R2 → o R2 principal não é uma segunda cópia
```

Nesta fase isso é aceitável, mas deve ficar documentado.

Se o risco exigir uma segunda camada futura, implementar replicação/cópia independente dos assets para outro bucket ou provedor.

Não confundir **storage remoto** com **backup independente**.

---

# PARTE D — CRITÉRIOS DE ACEITE

## 21. R2 — fotos e mapas

A implementação estará aceita quando:

- [ ] bucket `vistoria-assets` estiver privado;
- [ ] Laravel conseguir escrever, ler e excluir objetos;
- [ ] fotografias novas forem armazenadas no R2;
- [ ] mapas novos forem armazenados no R2;
- [ ] processamento assíncrono continuar funcionando;
- [ ] arquivos antigos tiverem sido migrados e conferidos;
- [ ] caminhos persistidos continuarem independentes de URL pública;
- [ ] isolamento por tenant continuar válido;
- [ ] relatórios utilizarem as imagens normalmente;
- [ ] falha no R2 não gerar falso status `ready`;
- [ ] credenciais não estiverem no Git.

## 22. Backup MySQL

A implementação estará aceita quando:

- [ ] existir bucket separado `vistoria-backups`;
- [ ] credenciais forem diferentes das utilizadas pelo Laravel;
- [ ] backup diário estiver automatizado;
- [ ] arquivo enviado estiver compactado;
- [ ] falhas gerarem código de erro/log identificável;
- [ ] política de retenção estiver configurada;
- [ ] pelo menos uma restauração completa tiver sido testada;
- [ ] o procedimento de recuperação estiver documentado.

---

# 23. Fora do escopo desta etapa

Não implementar agora:

- migração de `equipment_documents` para R2;
- bucket público;
- Cloudflare Images;
- CDN pública para fotografias privadas;
- upload direto do navegador para R2;
- presigned upload;
- cobrança por armazenamento;
- quotas por empresa;
- segunda cópia independente de fotos e mapas.

Esses itens podem ser tratados posteriormente.

---

# 24. Resumo da decisão

```text
FOTOS
Laravel → processamento → Cloudflare R2

MAPAS
Laravel → processamento → Cloudflare R2

MYSQL
mysqldump diário → gzip → bucket R2 separado

VPS
não será mais o armazenamento definitivo de fotos e mapas
```

A prioridade da implementação deve ser preservar a abstração de filesystem do Laravel e manter o domínio independente da Cloudflare.
