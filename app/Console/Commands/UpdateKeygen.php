<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generate the Ed25519 key pair that signs update packages.
 *
 * The SECRET key is written outside the project, to the developer's home
 * directory, and must never be committed or uploaded. Whoever holds it can
 * produce packages every site will install.
 *
 * The PUBLIC key goes into config/updates.php, which ships to every site. A
 * site can only verify packages; it cannot sign them.
 *
 * Losing the secret key does not break anything that is installed, but no
 * further one-file updates can be signed until a new key pair is generated and
 * the new public key reaches each site through a full deploy ZIP.
 */
class UpdateKeygen extends Command
{
    protected $signature = 'update:keygen
        {--key= : Where to write the secret key (default: ~/.apprmp/update-signing.key)}
        {--force : Overwrite an existing secret key — every site will then need a full deploy to trust the new one}
        {--write-config : Write the public key into config/updates.php}';

    protected $description = 'Generate the key pair used to sign one-file update packages';

    public function handle(): int
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->error('The sodium PHP extension is required.');
            return self::FAILURE;
        }

        $keyPath = $this->option('key') ?: self::defaultKeyPath();

        if (is_file($keyPath) && !$this->option('force')) {
            $this->error("A secret key already exists at {$keyPath}.");
            $this->line('Replacing it means no site will accept new packages until each receives the new public key through a full deploy ZIP. Use --force if that is really intended.');
            return self::FAILURE;
        }

        $dir = dirname($keyPath);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            $this->error("Cannot create {$dir}.");
            return self::FAILURE;
        }

        $pair   = sodium_crypto_sign_keypair();
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));

        file_put_contents($keyPath, $secret . PHP_EOL);
        @chmod($keyPath, 0600);

        $this->info('Update signing key pair generated.');
        $this->newLine();
        $this->line("  Secret key : {$keyPath}");
        $this->line('               KEEP THIS PRIVATE. Back it up somewhere safe. Never commit or upload it.');
        $this->line("  Public key : {$public}");
        $this->line('  Fingerprint: ' . substr(hash('sha256', base64_decode($public)), 0, 16));

        if ($this->option('write-config')) {
            $config = config_path('updates.php');
            $src    = (string) file_get_contents($config);
            $new    = preg_replace("/('public_key'\s*=>\s*)'[^']*'/", "\$1'{$public}'", $src, 1, $n);
            if ($n !== 1) {
                $this->error('Could not find the public_key entry in config/updates.php — set it by hand.');
                return self::FAILURE;
            }
            file_put_contents($config, $new);
            $this->info('config/updates.php updated. Commit it, and ship it to every site in a full deploy ZIP.');
        }

        return self::SUCCESS;
    }

    public static function defaultKeyPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();

        return rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.apprmp' . DIRECTORY_SEPARATOR . 'update-signing.key';
    }
}
