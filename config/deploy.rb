set :repo_url, 'git@github.com:coopcycle/coopcycle-web.git'

set :linked_files, fetch(:linked_files, [])
  .push(fetch(:app_config_path) + '/parameters.yml')

set :linked_dirs, fetch(:linked_dirs, [])
  .push(fetch(:var_path) + '/sessions', fetch(:var_path) + '/jwt', fetch(:web_path) + '/images')

set :symfony_directory_structure, 3
set :sensio_distribution_version, 5

set :format, :pretty
set :log_level, :info

set :pm2_config, "current/pm2.config.js"

# read https://github.com/capistrano/symfony/pull/37
namespace :symfony do
  desc "Fix cache permissions if deploy user is different from apache user"
  task :fix_file_permissions do
    on roles :all do
      paths = absolute_writable_paths
      execute :setfacl, '-R -m u:"www-data":rwX -m u:`whoami`:rwX', *paths
      execute :setfacl, '-dR -m u:"www-data":rwX -m u:`whoami`:rwX', *paths
    end
  end
end

SSHKit.config.command_map[:webpack] = "node_modules/.bin/webpack"

namespace :deploy do

  desc 'NPM install'
  task :npm_install do
    on roles :all do
      within release_path do
        execute :npm, 'install'
      end
    end
  end

  desc 'Webpack'
  task :webpack do
    on roles :all do
      within release_path do
        execute :webpack, '-p'
      end
    end
  end

  desc 'Restart PM2'
  task :restart_pm2 do
    on roles :all do
      within deploy_to do
        execute :pm2, 'restart', '--env', 'production', fetch(:pm2_config)
      end
    end
  end

  before :starting, :map_composer_command do
    on roles(:app) do |server|
      SSHKit.config.command_map[:composer] = "#{shared_path.join("composer.phar")}"
    end
  end

  after :starting, 'composer:install_executable'

  after :updated, 'symfony:fix_file_permissions'
  after :updated, 'npm_install'
  after :updated, 'webpack'

  after :finished, 'restart_pm2'

end
