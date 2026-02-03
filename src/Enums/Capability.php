<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

/**
 * Provider capabilities - features that may or may not be supported by each hosting provider
 */
enum Capability: string
{
    // Server Management
    case ServerManagement = 'server_management';
    case ServerProvisioning = 'server_provisioning';
    case CustomServer = 'custom_server';
    case SystemUserManagement = 'system_user_management';

    // Site Management
    case SiteProvisioning = 'site_provisioning';
    case SiteSuspension = 'site_suspension';
    case StagingSites = 'staging_sites';

    // SSL Management
    case SslInstallation = 'ssl_installation';
    case SslAutoRenewal = 'ssl_auto_renewal';

    // Backup Management
    case BackupCreation = 'backup_creation';
    case BackupRestore = 'backup_restore';

    // Database Management
    case DatabaseManagement = 'database_management';

    // PHP Management
    case PhpVersionSwitching = 'php_version_switching';

    // Cache Management
    case CacheClearing = 'cache_clearing';

    // Deployment
    case GitDeployment = 'git_deployment';
    case DeploymentScripts = 'deployment_scripts';

    // Workers & Jobs
    case QueueWorkers = 'queue_workers';
    case ScheduledJobs = 'scheduled_jobs';

    // WordPress Specific
    case WordPressManagement = 'wordpress_management';

    // Access Management
    case SshAccess = 'ssh_access';
    case FileManager = 'file_manager';

    // Email & DNS
    case EmailManagement = 'email_management';
    case DnsManagement = 'dns_management';

    // Monitoring
    case ResourceMonitoring = 'resource_monitoring';

    // Environment
    case EnvironmentVariables = 'environment_variables';

    public function label(): string
    {
        return match ($this) {
            self::ServerManagement => 'Server Management',
            self::ServerProvisioning => 'Server Provisioning',
            self::CustomServer => 'Custom Server',
            self::SystemUserManagement => 'System User Management',
            self::SiteProvisioning => 'Site Provisioning',
            self::SiteSuspension => 'Site Suspension',
            self::StagingSites => 'Staging Sites',
            self::SslInstallation => 'SSL Installation',
            self::SslAutoRenewal => 'SSL Auto Renewal',
            self::BackupCreation => 'Backup Creation',
            self::BackupRestore => 'Backup Restore',
            self::DatabaseManagement => 'Database Management',
            self::PhpVersionSwitching => 'PHP Version Switching',
            self::CacheClearing => 'Cache Clearing',
            self::GitDeployment => 'Git Deployment',
            self::DeploymentScripts => 'Deployment Scripts',
            self::QueueWorkers => 'Queue Workers',
            self::ScheduledJobs => 'Scheduled Jobs',
            self::WordPressManagement => 'WordPress Management',
            self::SshAccess => 'SSH Access',
            self::FileManager => 'File Manager',
            self::EmailManagement => 'Email Management',
            self::DnsManagement => 'DNS Management',
            self::ResourceMonitoring => 'Resource Monitoring',
            self::EnvironmentVariables => 'Environment Variables',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ServerManagement => 'Ability to list and manage existing servers',
            self::ServerProvisioning => 'Ability to provision new servers via cloud providers',
            self::CustomServer => 'Ability to connect existing/custom servers',
            self::SystemUserManagement => 'Create and manage system users on servers',
            self::SiteProvisioning => 'Ability to create new sites/accounts',
            self::SiteSuspension => 'Ability to suspend and unsuspend sites',
            self::StagingSites => 'Support for staging/development environments',
            self::SslInstallation => 'Ability to install SSL certificates',
            self::SslAutoRenewal => 'Automatic SSL certificate renewal (Let\'s Encrypt)',
            self::BackupCreation => 'Ability to create backups',
            self::BackupRestore => 'Ability to restore from backups',
            self::DatabaseManagement => 'Create and manage databases',
            self::PhpVersionSwitching => 'Ability to change PHP versions',
            self::CacheClearing => 'Ability to clear site/server cache',
            self::GitDeployment => 'Support for Git-based deployments',
            self::DeploymentScripts => 'Custom deployment script management',
            self::QueueWorkers => 'Support for queue worker management',
            self::ScheduledJobs => 'Support for cron/scheduled task management',
            self::WordPressManagement => 'WordPress-specific management features',
            self::SshAccess => 'SSH key and access management',
            self::FileManager => 'File management capabilities via API',
            self::EmailManagement => 'Email account creation and management',
            self::DnsManagement => 'DNS zone and record management',
            self::ResourceMonitoring => 'Server/site resource usage monitoring',
            self::EnvironmentVariables => 'Environment variable management',
        };
    }

    /**
     * Get all capabilities
     *
     * @return array<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get server-related capabilities
     *
     * @return array<self>
     */
    public static function serverCapabilities(): array
    {
        return [
            self::ServerManagement,
            self::ServerProvisioning,
            self::CustomServer,
            self::SystemUserManagement,
            self::ResourceMonitoring,
        ];
    }

    /**
     * Get site-related capabilities
     *
     * @return array<self>
     */
    public static function siteCapabilities(): array
    {
        return [
            self::SiteProvisioning,
            self::SiteSuspension,
            self::StagingSites,
            self::PhpVersionSwitching,
            self::CacheClearing,
        ];
    }

    /**
     * Get deployment-related capabilities
     *
     * @return array<self>
     */
    public static function deploymentCapabilities(): array
    {
        return [
            self::GitDeployment,
            self::DeploymentScripts,
            self::QueueWorkers,
            self::ScheduledJobs,
            self::EnvironmentVariables,
        ];
    }
}
