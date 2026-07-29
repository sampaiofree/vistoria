# 08 — Fotos e Armazenamento

## 1. Objetivo

Implementar o fluxo de captura, envio, processamento, armazenamento e uso das fotografias das inspeções.

As fotografias serão vinculadas às avaliações das avarias e servirão como:

- evidência técnica;
- apoio à revisão;
- conteúdo do relatório;
- histórico comparativo;
- registro da condição observada em campo.

A arquitetura inicial utilizará disco local privado no servidor.

A implementação deverá permitir migração futura para Cloudflare R2 sem alterar as regras de negócio.

---

## 2. Decisões aprovadas

### 2.1 Armazenamento inicial

No MVP será utilizado:

```text
disco local privado do servidor
```

As fotos não ficarão em:

```text
public/
```

O acesso será sempre controlado pelo Laravel.

---

### 2.2 Migração futura

A arquitetura deverá permitir troca futura para:

```text
Cloudflare R2
```

A aplicação usará sempre:

```php
Storage::disk('inspection_photos')
```

Nenhuma regra de negócio poderá depender de caminho físico do servidor.

---

### 2.3 Arquivo original

A fotografia original será preservada permanentemente.

Para cada fotografia serão armazenadas três versões:

```text
original
optimized
thumbnail
```

---

### 2.4 Acesso do cliente

No MVP, o cliente não terá acesso direto às fotografias originais.

O cliente receberá apenas:

```text
relatório PDF
```

---

### 2.5 Quantidade

Não haverá limite fixo de fotografias por inspeção ou avaria.

Haverá apenas:

- limite por arquivo;
- controle de espaço;
- paginação;
- processamento em fila;
- alertas de capacidade;
- validações de envio.

---

### 2.6 Segurança inicial

As fotografias serão tratadas como privadas, mesmo quando não forem classificadas como confidenciais.

---

## 3. Resultado esperado

Ao concluir esta etapa, o sistema deverá permitir:

- capturar fotografia pelo celular;
- selecionar fotografia da galeria;
- enviar uma ou várias imagens;
- mostrar progresso de upload;
- armazenar o original;
- gerar versão otimizada;
- gerar miniatura;
- corrigir orientação;
- registrar metadados;
- ordenar as fotografias;
- definir fotografia principal;
- adicionar legenda;
- remover fotografia durante a edição;
- impedir envio para revisão com processamento pendente;
- impedir envio para revisão sem evidência obrigatória;
- exibir galeria responsiva;
- visualizar imagem ampliada;
- usar versão otimizada no relatório;
- manter original privado;
- preservar fotografias após aprovação.

---

## 4. Escopo incluído

Será criado:

- disco privado `inspection_photos`;
- tabela `assessment_photos`;
- enum de status de processamento;
- enum de tipo de fotografia;
- upload temporário;
- armazenamento do original;
- processamento em fila;
- versão otimizada;
- miniatura;
- checksum;
- metadados;
- ordenação;
- imagem principal;
- legenda;
- Policies;
- Form Requests;
- Controllers;
- Actions;
- Jobs;
- páginas e componentes Vue;
- validação antes da revisão;
- testes;
- limpeza de uploads abandonados;
- preparação para migração futura ao R2.

---

## 5. Fora do escopo

Não será implementado agora:

- upload direto para R2;
- URL pré-assinada de upload;
- inteligência artificial;
- reconhecimento automático de avarias;
- OCR;
- anotação sobre a foto;
- desenho de círculos ou setas;
- edição avançada;
- sincronização offline;
- vídeo;
- áudio;
- acesso direto do cliente;
- CDN;
- migração real ao R2;
- backup externo automatizado completo.

O backup será tratado no documento de deploy.

---

# 6. Fluxo aprovado

```text
Celular
↓
Laravel recebe o arquivo
↓
Arquivo temporário privado
↓
Registro no banco como pending
↓
Job de processamento
↓
Validação técnica
↓
Correção de orientação
↓
Original movido para destino definitivo
↓
Versão otimizada criada
↓
Miniatura criada
↓
Metadados atualizados
↓
Status ready
```

Em caso de falha:

```text
status = failed
```

O usuário poderá tentar novamente ou remover o upload.

---

# 7. Estrutura de armazenamento

## 7.1 Desenvolvimento local

```text
storage/app/private/inspection-photos
```

---

## 7.2 Produção inicial

Recomendação:

```text
/data/vistoria/inspection-photos
```

Esse diretório deve ficar fora da pasta substituída durante o deploy.

Exemplo:

```text
/var/www/vistoria/current
/data/vistoria/inspection-photos
```

---

## 7.3 Estrutura lógica

```text
organizations/
└── {organization_id}/
    └── inspections/
        └── {inspection_public_id}/
            └── assessments/
                └── {assessment_public_id}/
                    └── photos/
                        └── {photo_public_id}/
                            ├── original/
                            │   └── original.ext
                            ├── optimized/
                            │   └── image.webp
                            └── thumbnail/
                                └── image.webp
```

---

## 7.4 Regra de caminho

O banco armazenará somente caminhos relativos.

Exemplo:

```text
organizations/15/inspections/01K.../assessments/01K.../photos/01K.../optimized/image.webp
```

Não armazenar:

```text
/data/vistoria/...
```

Não armazenar URL completa.

---

# 8. Configuração do disco

Adicionar em:

```text
config/filesystems.php
```

```php
'inspection_photos' => [
    'driver' => 'local',
    'root' => env(
        'INSPECTION_PHOTOS_ROOT',
        storage_path('app/private/inspection-photos'),
    ),
    'visibility' => 'private',
    'throw' => true,
],
```

No `.env` de produção:

```env
INSPECTION_PHOTOS_ROOT=/data/vistoria/inspection-photos
```

---

# 9. Versões da fotografia

## 9.1 Original

Regras:

- preservar o arquivo recebido;
- não alterar;
- não sobrescrever;
- calcular checksum;
- acesso restrito;
- não usar diretamente em listas;
- não usar diretamente no relatório.

---

## 9.2 Otimizada

Uso:

- galeria;
- tela de avaliação;
- relatório PDF;
- visualização ampliada padrão.

Configuração inicial:

```text
formato: WEBP
maior lado: 2560 px
qualidade: 80
```

Esses valores deverão ser validados com fotografias reais.

---

## 9.3 Miniatura

Uso:

- cards;
- listas;
- seleção;
- galeria móvel.

Configuração inicial:

```text
formato: WEBP
maior lado: 500 px
qualidade: 75
```

---

# 10. Formatos aceitos

Formatos iniciais:

```text
JPEG
JPG
PNG
WEBP
```

HEIC não será aceito inicialmente, salvo confirmação de suporte do servidor.

### Motivo

O suporte a HEIC depende de:

- ImageMagick;
- libheif;
- codecs instalados;
- configuração do servidor.

É melhor bloquear explicitamente do que aceitar e falhar no processamento.

---

# 11. Limites

## 11.1 Limite por arquivo

```text
25 MB
```

---

## 11.2 Dimensão mínima

Sugestão inicial:

```text
640 × 480 px
```

Fotos menores poderão ser rejeitadas ou marcadas com aviso de baixa resolução.

---

## 11.3 Quantidade por lote

Sugestão inicial:

```text
10 arquivos por envio
```

Não é limite por avaliação.

O usuário poderá enviar vários lotes.

---

## 11.4 Quantidade total

Sem limite fixo por inspeção no MVP.

---

# 12. Metadados

Cada fotografia deverá registrar:

```text
organization_id
inspection_id
defect_assessment_id
public_id
photo_type
caption
position
is_primary
processing_status
disk
original_path
optimized_path
thumbnail_path
original_name
original_mime_type
original_extension
original_size
original_width
original_height
optimized_size
optimized_width
optimized_height
thumbnail_size
thumbnail_width
thumbnail_height
checksum
captured_at
uploaded_at
processed_at
uploaded_by
processing_error
created_at
updated_at
deleted_at
```

---

# 13. Enums

## 13.1 `PhotoProcessingStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PhotoProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
```

---

## 13.2 `AssessmentPhotoType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AssessmentPhotoType: string
{
    case Overview = 'overview';
    case Detail = 'detail';
    case Context = 'context';
    case RepairEvidence = 'repair_evidence';
    case Other = 'other';
}
```

---

# 14. Migration `assessment_photos`

Comando:

```bash
php artisan make:model AssessmentPhoto -mf
```

Migration:

```php
<?php

declare(strict_types=1);

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_photos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('defect_assessment_id');

            $table->string('photo_type', 30)
                ->default(AssessmentPhotoType::Detail->value);

            $table->string('caption', 500)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_primary')->default(false);

            $table->string('processing_status', 30)
                ->default(PhotoProcessingStatus::Pending->value);

            $table->string('disk', 50)
                ->default('inspection_photos');

            $table->string('original_path', 700)->nullable();
            $table->string('optimized_path', 700)->nullable();
            $table->string('thumbnail_path', 700)->nullable();

            $table->string('original_name', 255);
            $table->string('original_mime_type', 120);
            $table->string('original_extension', 20)->nullable();

            $table->unsignedBigInteger('original_size');
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();

            $table->unsignedBigInteger('optimized_size')->nullable();
            $table->unsignedInteger('optimized_width')->nullable();
            $table->unsignedInteger('optimized_height')->nullable();

            $table->unsignedBigInteger('thumbnail_size')->nullable();
            $table->unsignedInteger('thumbnail_width')->nullable();
            $table->unsignedInteger('thumbnail_height')->nullable();

            $table->char('checksum', 64)->nullable();

            $table->timestamp('captured_at')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('processed_at')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('processing_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'assessment_photos_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'inspection_id', 'defect_assessment_id'],
                'assessment_photos_org_assessment_foreign',
            )
                ->references([
                    'organization_id',
                    'inspection_id',
                    'id',
                ])
                ->on('defect_assessments')
                ->restrictOnDelete();

            $table->index(
                [
                    'organization_id',
                    'defect_assessment_id',
                    'processing_status',
                ],
                'assessment_photos_org_assessment_status_index',
            );

            $table->index(
                [
                    'organization_id',
                    'inspection_id',
                    'position',
                ],
                'assessment_photos_org_inspection_position_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_photos');
    }
};
```

---

## 14.1 Ajuste em `defect_assessments`

Para permitir a chave composta, adicionar índice:

```php
$table->unique(
    ['organization_id', 'inspection_id', 'id'],
    'defect_assessments_org_inspection_id_unique',
);
```

Caso a migration já tenha sido executada, criar uma nova migration.

---

# 15. Model `AssessmentPhoto`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class AssessmentPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\AssessmentPhotoFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'inspection_id',
        'defect_assessment_id',
        'photo_type',
        'caption',
        'position',
        'is_primary',
        'processing_status',
        'disk',
        'original_path',
        'optimized_path',
        'thumbnail_path',
        'original_name',
        'original_mime_type',
        'original_extension',
        'original_size',
        'original_width',
        'original_height',
        'optimized_size',
        'optimized_width',
        'optimized_height',
        'thumbnail_size',
        'thumbnail_width',
        'thumbnail_height',
        'checksum',
        'captured_at',
        'uploaded_at',
        'processed_at',
        'uploaded_by',
        'processing_error',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssessmentPhoto $photo): void {
            $photo->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'photo_type' => AssessmentPhotoType::class,
            'processing_status' => PhotoProcessingStatus::class,
            'is_primary' => 'boolean',
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DefectAssessment::class,
            'defect_assessment_id',
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->processing_status === PhotoProcessingStatus::Ready;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 16. Relacionamentos

## 16.1 `DefectAssessment`

Adicionar:

```php
public function photos(): HasMany
{
    return $this->hasMany(
        AssessmentPhoto::class,
        'defect_assessment_id',
    )->orderBy('position');
}
```

---

## 16.2 `Inspection`

Adicionar:

```php
public function assessmentPhotos(): HasMany
{
    return $this->hasMany(AssessmentPhoto::class);
}
```

---

# 17. Biblioteca de processamento

A biblioteca será escolhida durante a implementação.

Requisitos mínimos:

- leitura de JPEG, PNG e WEBP;
- correção de orientação EXIF;
- redimensionamento;
- conversão para WEBP;
- compressão;
- leitura de dimensões;
- compatibilidade com filas;
- funcionamento no servidor Hetzner.

Opções possíveis:

```text
Intervention Image
Spatie Image
ImageMagick diretamente
```

A decisão deve considerar a compatibilidade real com Laravel 13 e PHP 8.3 no momento da implementação.

---

# 18. Action `StoreAssessmentPhoto`

Criar:

```text
app/Actions/Photos/StoreAssessmentPhoto.php
```

Responsabilidades:

1. validar organização;
2. validar inspeção;
3. validar avaliação;
4. validar estado editável;
5. criar registro como `pending`;
6. armazenar original em diretório temporário;
7. calcular posição;
8. disparar Job;
9. remover arquivo em caso de falha.

---

## 18.1 Estado editável

O upload será permitido apenas quando a inspeção estiver:

```text
in_progress
in_correction
```

---

## 18.2 Posição

A próxima posição será:

```text
MAX(position) + 1
```

dentro da avaliação.

Usar transação para evitar posições duplicadas.

---

# 19. Job `ProcessAssessmentPhoto`

Criar:

```bash
php artisan make:job ProcessAssessmentPhoto
```

Fila:

```text
images
```

Responsabilidades:

1. receber `organization_id` e `photo_id`;
2. carregar registro;
3. validar tenant explícito;
4. marcar como `processing`;
5. verificar o original;
6. validar MIME real;
7. ler dimensões;
8. corrigir orientação;
9. gerar otimizada;
10. gerar miniatura;
11. calcular tamanhos;
12. calcular checksum do original;
13. mover arquivos para destino definitivo;
14. atualizar registro;
15. marcar como `ready`;
16. limpar temporários.

---

## 19.1 Configuração do Job

Sugestão:

```php
public int $tries = 3;
public int $timeout = 180;
```

Backoff:

```php
public function backoff(): array
{
    return [10, 60, 300];
}
```

---

## 19.2 Falha

No método `failed()`:

- marcar `processing_status = failed`;
- registrar mensagem segura;
- manter original temporário quando possível;
- registrar log;
- permitir reprocessamento.

Não gravar stack trace completo no banco.

---

# 20. Action `RetryAssessmentPhotoProcessing`

Permitida quando:

```text
processing_status = failed
```

Regras:

- mesmo tenant;
- arquivo original existente;
- inspeção editável;
- limpar erro anterior;
- voltar para `pending`;
- reenviar Job.

---

# 21. Action `ReorderAssessmentPhotos`

Entrada:

```json
[
  {"public_id": "...", "position": 1},
  {"public_id": "...", "position": 2}
]
```

Regras:

- todas pertencem à mesma avaliação;
- todas pertencem ao tenant;
- sem duplicidade;
- posições sequenciais;
- inspeção editável;
- transação.

---

# 22. Action `SetPrimaryAssessmentPhoto`

Regras:

- fotografia pronta;
- mesma avaliação;
- desmarcar principal anterior;
- marcar uma única principal;
- transação;
- `lockForUpdate()`.

---

# 23. Remoção

## 23.1 Durante edição

Permitida em:

```text
in_progress
in_correction
```

Fluxo:

- soft delete do registro;
- Job de remoção física;
- reorganizar posições;
- registrar ação futuramente na auditoria.

---

## 23.2 Após aprovação

Não permitir remoção simples.

Será necessário reabrir a inspeção no fluxo futuro de auditoria.

---

# 24. Action de download ou visualização

Criar:

```text
ShowAssessmentPhoto
DownloadOriginalAssessmentPhoto
```

### Versão otimizada

Usada pela interface.

### Original

Acesso apenas para usuários internos autorizados.

O cliente não terá rota direta.

---

## 24.1 Acesso local privado

O Controller poderá usar:

```php
Storage::disk($photo->disk)->response(
    $photo->optimized_path,
    headers: [
        'Cache-Control' => 'private, max-age=300',
    ],
);
```

Para o original:

```php
Storage::disk($photo->disk)->download(
    $photo->original_path,
    $photo->original_name,
);
```

Sempre após Policy.

---

# 25. Policies

Criar:

```text
AssessmentPhotoPolicy
```

Permissões:

```text
view
viewOriginal
create
update
delete
reorder
setPrimary
retry
```

---

## 25.1 Visualização

Usuários internos ativos da organização poderão visualizar a versão otimizada.

---

## 25.2 Original

Somente:

- administrador interno;
- inspetor responsável;
- preparador;
- revisor;
- aprovador;
- liberador.

---

## 25.3 Escrita

Somente usuário com responsabilidade adequada e inspeção editável.

---

# 26. Form Requests

Criar:

```text
Photos/StoreAssessmentPhotoRequest
Photos/UpdateAssessmentPhotoRequest
Photos/ReorderAssessmentPhotosRequest
Photos/SetPrimaryAssessmentPhotoRequest
```

---

## 26.1 Upload

Regras:

```php
use Illuminate\Validation\Rules\File;

'photos' => [
    'required',
    'array',
    'min:1',
    'max:10',
],

'photos.*' => [
    'required',
    File::image()
        ->types(['jpg', 'jpeg', 'png', 'webp'])
        ->max(25 * 1024),
],
```

---

## 26.2 Legenda

```text
nullable
string
máximo 500 caracteres
```

---

## 26.3 Tipo

Usar:

```php
Rule::enum(AssessmentPhotoType::class)
```

---

# 27. Controllers

Criar:

```bash
php artisan make:controller AssessmentPhotoController
php artisan make:controller AssessmentPhotoOrderController
```

---

## 27.1 `AssessmentPhotoController`

Métodos:

```text
store
show
showOriginal
update
destroy
retry
setPrimary
```

---

## 27.2 `AssessmentPhotoOrderController`

Método:

```text
update
```

---

# 28. Rotas

```php
Route::post(
    'defect-assessments/{defectAssessment}/photos',
    [AssessmentPhotoController::class, 'store'],
)->name('defect-assessments.photos.store');

Route::get(
    'assessment-photos/{assessmentPhoto}',
    [AssessmentPhotoController::class, 'show'],
)->name('assessment-photos.show');

Route::get(
    'assessment-photos/{assessmentPhoto}/original',
    [AssessmentPhotoController::class, 'showOriginal'],
)->name('assessment-photos.original');

Route::patch(
    'assessment-photos/{assessmentPhoto}',
    [AssessmentPhotoController::class, 'update'],
)->name('assessment-photos.update');

Route::delete(
    'assessment-photos/{assessmentPhoto}',
    [AssessmentPhotoController::class, 'destroy'],
)->name('assessment-photos.destroy');

Route::post(
    'assessment-photos/{assessmentPhoto}/retry',
    [AssessmentPhotoController::class, 'retry'],
)->name('assessment-photos.retry');

Route::post(
    'assessment-photos/{assessmentPhoto}/primary',
    [AssessmentPhotoController::class, 'setPrimary'],
)->name('assessment-photos.primary');

Route::patch(
    'defect-assessments/{defectAssessment}/photos/order',
    [AssessmentPhotoOrderController::class, 'update'],
)->name('defect-assessments.photos.order');
```

---

# 29. Frontend

Componentes:

```text
AssessmentPhotoUploader.vue
AssessmentPhotoGallery.vue
AssessmentPhotoCard.vue
AssessmentPhotoViewer.vue
AssessmentPhotoStatusBadge.vue
AssessmentPhotoCaptionForm.vue
AssessmentPhotoReorder.vue
```

---

## 29.1 Upload no celular

O input deverá permitir:

```html
<input
    type="file"
    accept="image/jpeg,image/png,image/webp"
    capture="environment"
    multiple
/>
```

O atributo `capture` pode sugerir a câmera traseira em dispositivos móveis.

Não deve ser tratado como garantia em todos os navegadores.

---

## 29.2 Progresso

Mostrar por arquivo:

```text
Enviando
Processando
Pronta
Falhou
```

---

## 29.3 Prévia

A prévia local pode ser exibida antes do envio.

Depois do upload, usar a miniatura gerada pelo servidor.

---

## 29.4 Falha

Mostrar:

- mensagem simples;
- botão reenviar;
- botão remover;
- não bloquear silenciosamente a tela.

---

# 30. Validação antes da revisão

Criar:

```text
AssessmentPhotoCoverageValidator
```

Antes de enviar para revisão:

- não pode existir foto `pending`;
- não pode existir foto `processing`;
- não pode existir foto `failed`;
- avaliações que exigem evidência devem possuir pelo menos uma foto `ready`.

---

## 30.1 Condições que exigem foto

```text
new
unchanged
worsened
improved
repaired
```

---

## 30.2 Condições sem foto obrigatória

```text
not_located
not_inspected
```

Essas exigem justificativa.

---

## 30.3 Integração

A Action:

```text
SubmitInspectionForReview
```

deverá chamar:

```php
$photoCoverageValidator->validate($inspection);
```

---

# 31. Limpeza de arquivos temporários

Criar comando:

```bash
php artisan make:command CleanupAbandonedPhotoUploads
```

Critério inicial:

```text
uploads pending há mais de 24 horas
sem processamento ativo
```

O comando poderá:

- remover arquivos temporários;
- marcar registro como `failed`;
- registrar log.

Agendamento:

```php
Schedule::command('photos:cleanup-abandoned')
    ->dailyAt('03:00');
```

O nome real do comando deverá ser definido na implementação.

---

# 32. Monitoramento de espaço

Criar comando futuro ou inicial:

```text
CheckPhotoStorageCapacity
```

Alertas sugeridos:

```text
70% → atenção
85% → crítico
95% → bloquear novos uploads
```

No MVP, pelo menos registrar em log.

No deploy, configurar alerta externo.

---

# 33. Backup

O disco local não poderá ser a única cópia em produção.

Requisito:

```text
backup externo diário
```

O documento 13 definirá:

- destino;
- retenção;
- criptografia;
- teste de restauração;
- monitoramento.

---

# 34. Preparação para R2

A migração futura deverá exigir apenas:

1. configurar novo disk;
2. copiar arquivos;
3. atualizar `disk` dos registros;
4. validar checksums;
5. trocar leitura e escrita;
6. ativar URLs temporárias.

O domínio não deverá ser alterado.

---

# 35. Factories

Criar:

```text
AssessmentPhotoFactory
```

States:

```text
pending
processing
ready
failed
primary
```

A factory deve receber explicitamente:

```text
organization
inspection
assessment
```

---

# 36. Testes obrigatórios

Criar:

```text
tests/Feature/Photos/
tests/Unit/Photos/
```

---

## 36.1 Upload

Testar:

- usuário autorizado envia foto;
- membro não responsável não envia;
- tenant vem do backend;
- arquivo fica privado;
- registro é criado como `pending`;
- Job é disparado;
- limite de 25 MB;
- MIME inválido é rejeitado;
- extensão falsa é rejeitada;
- outro tenant é bloqueado.

---

## 36.2 Processamento

Testar:

- original é preservado;
- otimizada é criada;
- miniatura é criada;
- dimensões são registradas;
- checksum é gravado;
- status vira `ready`;
- temporário é removido;
- falha marca `failed`;
- retry funciona.

---

## 36.3 Relacionamento

Testar:

- foto pertence à avaliação correta;
- avaliação pertence à inspeção;
- inspeção pertence ao mesmo equipamento;
- vínculo cruzado é bloqueado;
- chave composta funciona.

---

## 36.4 Permissões

Testar:

- usuário interno autorizado visualiza;
- outro tenant não visualiza;
- cliente não possui rota direta;
- original exige permissão;
- inspeção aprovada bloqueia remoção;
- inspeção em correção permite edição.

---

## 36.5 Ordenação

Testar:

- reordena;
- posições permanecem sequenciais;
- não mistura avaliações;
- não aceita foto de outro tenant;
- mantém apenas uma principal.

---

## 36.6 Revisão

Testar:

- bloqueia envio com foto pendente;
- bloqueia envio com foto processando;
- bloqueia envio com foto falha;
- bloqueia avaliação sem foto obrigatória;
- permite `not_inspected` com justificativa;
- permite envio quando todas estão prontas.

---

## 36.7 Filesystem

Usar:

```php
Storage::fake('inspection_photos');
```

Confirmar:

```php
Storage::disk('inspection_photos')
    ->assertExists($photo->original_path);
```

---

# 37. Validação manual

1. Abrir avaliação no celular.
2. Tirar foto.
3. Enviar.
4. Ver progresso.
5. Confirmar miniatura.
6. Abrir versão otimizada.
7. Abrir original com permissão.
8. Alterar legenda.
9. Reordenar.
10. Definir principal.
11. Enviar foto inválida.
12. Simular falha.
13. Reprocessar.
14. Tentar enviar inspeção com foto pendente.
15. Confirmar bloqueio.
16. Completar processamento.
17. Confirmar envio.
18. Verificar arquivos no disco.
19. Confirmar ausência em `public/`.

---

# 38. Critérios de aceite

- [ ] disco privado configurado;
- [ ] caminho físico fora do deploy em produção;
- [ ] tabela `assessment_photos` criada;
- [ ] original preservado;
- [ ] versão otimizada criada;
- [ ] miniatura criada;
- [ ] processamento em fila;
- [ ] status de processamento funciona;
- [ ] checksum registrado;
- [ ] metadados registrados;
- [ ] upload pelo celular funciona;
- [ ] galeria responsiva;
- [ ] ordenação funciona;
- [ ] fotografia principal funciona;
- [ ] original possui acesso restrito;
- [ ] cliente não acessa diretamente;
- [ ] envio para revisão bloqueia pendências;
- [ ] isolamento multiempresa funciona;
- [ ] testes passam;
- [ ] build passa;
- [ ] documentação corresponde ao código.

---

# 39. Riscos e brechas

## 39.1 Disco local sem backup

Falha no servidor pode eliminar evidências.

Mitigação:

- backup externo obrigatório;
- teste de restauração;
- migração futura para R2.

---

## 39.2 Arquivo dentro da pasta do deploy

Um deploy pode apagar fotos.

Mitigação:

- usar `/data/vistoria`;
- configurar por variável de ambiente.

---

## 39.3 Aceitar extensão sem validar conteúdo

Arquivo malicioso pode ser renomeado como imagem.

Mitigação:

- validar MIME;
- tentar decodificar;
- armazenar fora de `public/`;
- gerar nomes internos.

---

## 39.4 Sobrecarga do PHP

Muitas fotos grandes podem consumir memória e CPU.

Mitigação:

- upload em lotes pequenos;
- processamento por fila;
- limites;
- worker separado.

---

## 39.5 Original exposto

URL pública permanente expõe evidência interna.

Mitigação:

- rota autorizada;
- bucket ou disco privado;
- nenhuma URL pública fixa.

---

## 39.6 Fotos processando para sempre

Job pode falhar sem feedback.

Mitigação:

- status;
- timeout;
- retries;
- alerta;
- botão de reprocessamento.

---

## 39.7 Arquivos órfãos

Falhas podem deixar arquivo sem registro.

Mitigação:

- limpeza periódica;
- checksum;
- convenção de caminhos;
- logs.

---

## 39.8 Duplicação

Usuário pode enviar a mesma foto várias vezes.

Mitigação futura:

- comparar checksum dentro da avaliação;
- alertar, sem bloquear automaticamente no MVP.

---

## 39.9 Remoção após aprovação

Excluir foto alteraria a evidência do relatório.

Mitigação:

- bloquear;
- reabertura formal;
- nova revisão.

---

# 40. Checklist de execução

- [ ] Configurar disco privado.
- [ ] Criar enums.
- [ ] Criar migration.
- [ ] Adicionar índice à avaliação.
- [ ] Criar model.
- [ ] Atualizar relacionamentos.
- [ ] Escolher biblioteca de imagem.
- [ ] Criar Action de upload.
- [ ] Criar Job de processamento.
- [ ] Criar retry.
- [ ] Criar ordenação.
- [ ] Criar foto principal.
- [ ] Criar remoção.
- [ ] Criar visualização.
- [ ] Criar Policies.
- [ ] Criar Form Requests.
- [ ] Criar Controllers.
- [ ] Criar rotas.
- [ ] Criar componentes Vue.
- [ ] Criar validador de cobertura.
- [ ] Atualizar envio para revisão.
- [ ] Criar limpeza de temporários.
- [ ] Criar factories.
- [ ] Criar testes de upload.
- [ ] Criar testes de processamento.
- [ ] Criar testes de permissão.
- [ ] Criar testes de revisão.
- [ ] Executar migration.
- [ ] Executar Pint.
- [ ] Executar testes.
- [ ] Executar build.
- [ ] Validar manualmente.
- [ ] Atualizar roadmap.
- [ ] Criar commit.

---

# 41. Commit sugerido

```bash
git add .
git commit -m "feat: add private inspection photo workflow"
```

---

# 42. Próximo documento

```text
09-CLASSIFICACAO-CIVIL-GUT.md
```

O próximo documento definirá:

- gravidade;
- urgência;
- tendência;
- cálculo GUT;
- classificação CV;
- tipos de dano;
- elementos;
- quantitativos;
- comentários;
- recomendações;
- regras versionadas;
- validações;
- testes.
