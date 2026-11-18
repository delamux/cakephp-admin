<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Behind a load balancer / reverse proxy: trust X-Forwarded-For/-Proto only when the connection
 * comes from one of the configured proxies (TRUSTED_PROXIES), so `clientIp()` (throttling, logs,
 * account activity) is the real client, the scheme is the one the client used (TLS ended at the
 * proxy) and nobody else can spoof them. Entries are IPs or CIDR ranges (IPv4/IPv6): on Coolify the
 * proxy containers change address on every deploy, so a private range is trusted. Without proxies
 * configured nothing changes.
 */
class TrustedProxiesMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $proxies Proxy IPs or CIDR ranges.
     */
    public function __construct(private array $proxies)
    {
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->proxies !== [] && $request instanceof ServerRequest) {
            // CakePHP compares exact addresses: pass the concrete ones that fall in the ranges (the
            // peer and the forwarded hops). It sets trustProxy only when the peer is one of them.
            $candidates = array_merge(
                [(string)$request->getEnv('REMOTE_ADDR')],
                array_map('trim', explode(',', (string)$request->getEnv('HTTP_X_FORWARDED_FOR'))),
            );
            $trusted = array_values(array_unique(array_filter(
                $candidates,
                fn(string $ip): bool => $ip !== '' && $this->isTrusted($ip),
            )));
            $request->setTrustedProxies($trusted !== [] ? $trusted : $this->proxies);
        }

        return $handler->handle($request);
    }

    /**
     * @param string $ip Address.
     * @return bool
     */
    private function isTrusted(string $ip): bool
    {
        foreach ($this->proxies as $proxy) {
            if (str_contains($proxy, '/') ? self::inRange($ip, $proxy) : $ip === $proxy) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ip Address.
     * @param string $cidr Range, e.g. `10.0.0.0/8` or `fd00::/8`.
     * @return bool
     */
    public static function inRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2) + [1 => ''];
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }
        $bits = (int)$bits;
        if ($bits < 0 || $bits > strlen($ipBinary) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (strncmp($ipBinary, $subnetBinary, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = 0xFF << (8 - $rest) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
