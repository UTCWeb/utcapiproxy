// This is adapted from /vendor/koterle/envoy-oven
// DEPLOYMENT COMMANDS:
// sail
// for zzbernardo: sail bin envoy run deploy
// for prod blog: sail bin envoy run deploy-prod --env=prod
@setup
    // set up timezone
    date_default_timezone_set('America/New_York');

    // We can load the config via a project argument JSON encoded string or
    // via the envoy.config.php file; we use envoy.config.php
    if (isset($project)) {
        $project = json_decode($project, true);
    } else {
        $project = include('envoy.config.php');
    }

    if (!isset($project['deploy_server'])) {
        throw new Exception('Deployment server is not set');
    }

    if (!isset($project['deploy_tactic'])) {
        throw new Exception('Deployment tactic is not set');
    }

    if (!isset($project['deploy_path'])) {
        throw new Exception('Deployment path is not set');
    }

    if (substr($project['deploy_path'], 0, 1) !== '/' ) {
        throw new Exception('Deploy path does not begin with /');
    }

    if (!isset($project['repository'])) {
        throw new Exception('Repository is not set');
    }

    // Append / at the end of the path
    //$base_dir = rtrim($project['deploy_path'], '/') . '/';
    $environment = isset($env) ? $env : 'test';
    switch ($environment) {
        case 'test':
            $base_dir = rtrim($project['deploy_path'], '/') . '/';
            $branch = isset($project['branch_default']) ? $project['branch_default'] : 'develop';
            break;
        case 'prod':
            $base_dir = rtrim($project['prod_path'], '/') . '/';
            $branch = isset($project['branch_main']) ? $project['branch_main'] : 'main';
            break;
        default:
            $base_dir = rtrim($project['deploy_path'], '/') . '/';
            $branch = isset($project['branch_default']) ? $project['branch_default'] : 'develop';
    }

    // Setting some sensible defaults
    $repository = $project['repository'];
    $releases_dir = $base_dir . (isset($project['dirs']['releases']) ? $project['dirs']['releases'] : 'releases');
    $current_dir = $base_dir . (isset($project['dirs']['current']) ? $project['dirs']['current'] : 'current');
    $shared_dir = $base_dir . (isset($project['dirs']['shared']) ? $project['dirs']['shared'] : 'shared');
    $release = date("Ymd-His");

    if (! $branch) {
        $branch = isset($project['branch_default']) ? $project['branch_default'] : 'develop';
    }

    $public_dir = isset($project['public_dir']) ? $project['public_dir'] : 'public';
    $composer_install = isset($project['composer_install']) ? $project['composer_install'] : true;
    $npm_install = isset($project['npm_install']) ? $project['npm_install'] : true;
    $release_keep_count = isset($project['release_keep_count']) ? $project['release_keep_count'] : 5;
    $node_version = isset($project['node_version']) ? $project['node_version'] : false;
    $reload_services = isset($project['reload_services']) ? $project['reload_services'] : ['nginx', 'php7.3-fpm'];
@endsetup

@servers(['test' => $project['deploy_server'], 'prod' => $project['prod_server']])

@story('deploy', [ 'on' => 'test' ])
    fetch
    composer
    {{-- npm --}}
    permissions
    {{ $project['deploy_tactic'] }}
    symlink
    {{-- permalinks --}}
    flush
    purge_old
@endstory

@story('deploy-prod', [ 'on' => 'prod' ])
    fetch
    composer
    {{-- npm --}}
    permissions
    {{ $project['deploy_tactic'] }}
    symlink
    {{-- permalinks --}}
    flush
    purge_old
@endstory

@task('fetch')
    echo 'Deploying from branch {{ $branch }}'

    echo 'Preparing directories: {{ $base_dir }}';
    [ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }};
    cd {{ $releases_dir }};

    git clone -b {{ $branch }} --depth=1 {{ $repository }} {{ $release }};
@endtask

@task('composer')
    # run composer install if needed
    @if ($composer_install)
        echo 'Installing Composer dependencies';
        cd {{ $releases_dir }}/{{ $release }};
        composer clear-cache;
        composer install --prefer-dist --no-scripts --no-dev --apcu-autoloader -q -o;
    @endif
@endtask

{{-- @task('npm') --}}
    {{-- echo 'Running front-end build for UTC theme'
    # run npm install if needed
    @if ($npm_install)
        echo 'Installing npm dependencies';
        cd {{ $releases_dir }}/{{ $release }}/web/app/themes/utc-tailwind-genesis-theme;

        @if ($node_version)
            . ~/.nvm/nvm.sh;
            . ~/.profile;
            . ~/.bashrc;
            nvm use {{ $node_version }};
        @endif
        npm install;
        npm run prod;
    @endif
    echo 'Removing node_modules after build';
    rm -rf {{ $releases_dir }}/{{ $release }}/web/app/themes/utc-tailwind-genesis-theme/node_modules; --}}
{{-- @endtask --}}

@task('permissions')
    echo 'Setting up permissions'
    cd {{ $releases_dir}};
    find {{ $release }} -type d -exec chmod 755 {} +
    find {{ $release }} -type f -exec chmod 644 {} +
@endtask

@task('symlink')
    # Symlink the latest release to the current directory
    echo 'Linking current release'. {{ $releases_dir }}/{{ $release }} {{ $public_dir }}
    ln -nfs {{ $releases_dir }}/{{ $release }}/* {{ $public_dir }}
@endtask

@task('flush')
    # Laravel Disk Cache setup
    {{-- echo 'Linking cache directory';
    rm -rf {{ $releases_dir }}/{{ $release }} storage;
    cd {{ $releases_dir }}/{{ $release }};
    ln -nfs {{ $shared_dir }}/storage storage; --}}

    # Laravel update db
    cd {{ $public_dir }}
    echo 'Updating Laravel database' . {{ $public_dir }}
    php artisan migrate --force

    # Flushing Laravel Cache
    echo 'Flushing Laravel Cache';
    php artisan cache:clear;

@endtask

@task('reload')
    @foreach ($reload_services as $service)
        echo 'Reloading: {{ $service }}'
        sudo /usr/sbin/service {{ $service }} reload
    @endforeach
@endtask

@task('purge_old')
    @if ($release_keep_count != -1)
        echo 'Purging old releases';
        # This will list our releases by modification time and delete all but the 5 most recent
        ls -dt {{ $releases_dir }}/* | tail -n +{{ $release_keep_count + 1 }} | xargs -d '\n' rm -rf
    @endif
@endtask

@task('rollback-test', [ 'on' => 'test' ])
    echo 'Rolling back to previous release';
    cd {{ $releases_dir }}
    ln -nfs $(find {{ $releases_dir }} -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1) {{ $public_dir }}
	echo "Rolled back to $(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1)"
@endtask

@task('rollback-prod', [ 'on' => 'prod' ])
    echo 'Rolling back to previous release';
    cd {{ $releases_dir }}
    ln -nfs $(find {{ $releases_dir }} -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1) {{ $public_dir }}
    echo "Rolled back to $(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1)"
@endtask

@task('laravel')
    echo 'Laravel deployment'
    # Import the environment config
    echo 'Linking shared .env file';
    cd {{ $releases_dir }}/{{ $release }}
    ln -nfs {{ $shared_dir }}/.env .env

    {{-- echo 'Linking upload directory';
    rm -rf {{ $releases_dir }}/{{ $release }} public;
    cd {{ $releases_dir }}/{{ $release }};
    ln -nfs {{ $shared_dir }}/public public; --}}
@endtask

{{-- @task('permalinks')
    echo 'linking shared .htaccess file';
    cd {{ $releases_dir }}/{{ $release }}/web;
    ln -nfs {{ $shared_dir }}/.htaccess .htaccess;
@endtask --}}
