# Cola de trabajos

[dereuromark/cakephp-queue](https://github.com/dereuromark/cakephp-queue): trabajos en segundo plano guardados en
**PostgreSQL** (tablas `queued_jobs` y `queue_processes`, con `SKIP LOCKED`). Sin Redis ni servicios externos.

## Emails: todos por la cola

El perfil `Email.default` usa el transporte `queue` (`config/app.php`), que guarda el mensaje **ya renderizado**
(layout y CSS en línea incluidos) como un trabajo `Queue.Email`; el worker lo entrega por el transporte real
(`default`, `EMAIL_TRANSPORT_DEFAULT_URL`). La petición no espera al SMTP y, si el SMTP cae, se reintenta.

- Afecta a todo: registro, reset, enlace mágico, avisos de 2FA… sin tocar ningún mailer.
- `EMAIL_QUEUE=false` vuelve a enviar durante la petición (por ejemplo, en una instalación sin worker).
- En los tests no cambia nada: `EmailTrait` sustituye los transportes.

## El worker

`bin/cake queue run`: procesa trabajos y sale tras `Queue.workerLifetime` (600 s + un margen aleatorio); Docker lo
vuelve a arrancar (`restart: unless-stopped`). Es el servicio **`worker`** en `compose.yaml` (dev),
`compose.prod.yaml` y `compose.coolify.yaml`, con la misma imagen que la app.

- Con `pcntl` (instalado en la imagen), al pararlo (`docker stop`, redeploy) termina el trabajo en curso antes de
  salir (`stop_grace_period: 60s`).
- Varias réplicas del worker reparten el trabajo sin pisarse.
- En dev, tras cambiar código que use el worker: `docker compose restart worker`.

| Config (`Queue` en `config/app.php`) | Valor | Para qué |
| --- | --- | --- |
| `workerLifetime` | `QUEUE_WORKER_LIFETIME` (600) | Segundos antes de reciclar el proceso |
| `sleeptime` | `QUEUE_SLEEPTIME` (5) | Espera cuando no hay trabajo (lo que tarda en salir un email) |
| `defaultJobRetries` | 3 | Reintentos tras el primer intento |
| `maxworkers` | 1 | Workers por servidor (uno por contenedor) |
| `cleanuptimeout` / `cleanupAbortedTimeout` | 7 / 30 días | Cuánto se guardan los terminados y los fallidos |

## La pantalla *System › Queue* (superadmin)

`/queue`: contadores (en espera, en curso, programados, fallidos, hechos en 24 h), **trabajos fallidos con su error**
(reintentar uno, reintentar todos, borrar), los últimos 20 trabajos y los workers vivos. Si no hay ningún worker,
lo avisa. Nunca muestra los datos de los trabajos (los emails llevan datos personales).

La pantalla propia del plugin (`/admin/queue`) no tiene rutas: carga Bootstrap desde un CDN, incompatible con la CSP.

## Fallos

- Un intento fallido → `warning` en el log; un trabajo que agota sus reintentos → `error` en el log (en producción,
  stderr: lo recoge Docker/Coolify) y queda en la pantalla hasta reintentarlo o borrarlo. Solo la primera línea del
  error: sin traza ni datos.
- Terminados y fallidos antiguos los borra `bin/cake maintenance`.

```bash
bin/cake queue info                 # estado y estadísticas (--format json para monitorizar)
bin/cake queue job view <id>        # un trabajo
bin/cake queue worker               # workers registrados
```

## Crear una tarea propia

```php
// src/Queue/Task/ImportCustomersTask.php
namespace App\Queue\Task;

use Queue\Queue\Task;

class ImportCustomersTask extends Task
{
    public ?int $timeout = 300;   // segundos (≤ Queue.defaultRequeueTimeout)
    public ?int $retries = 2;

    public function run(array $data, int $jobId): void
    {
        $file = $this->fetchTable('Imports')->get($data['import_id']);
        // ... trabajo; una excepción = intento fallido (se reintenta)
        $this->QueuedJobs->updateProgress($jobId, 0.5);
    }
}
```

```php
// Encolar (desde un caso de uso): pasa IDs, no entidades; el worker lee los datos actuales
$this->fetchTable('Queue.QueuedJobs')->createJob('ImportCustomers', ['import_id' => $import->id], [
    'reference' => 'import-' . $import->id,   // con 'unique' => true, evita duplicados pendientes
]);
```

**Idempotencia:** un trabajo puede ejecutarse dos veces (worker caído en mitad, claim caducado). Diseña las tareas
para que repetirlas no haga daño: comprueba el estado antes de actuar, usa restricciones únicas o el soporte de
idempotencia del servicio externo. En los emails, el caso raro es un correo duplicado.

Para enviar un email propio no hace falta tarea: cualquier `Mailer` ya pasa por la cola.
