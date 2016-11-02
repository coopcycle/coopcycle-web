# config valid only for current version of Capistrano
lock '3.6.1'

set :user, 'capistrano'
set :use_sudo, false

set :application, 'coursiers'
set :repo_url, 'git@gitlab.com:coursiers-coop/web2.git'
# set :linked_files, %w{app/config/parameters.yml app/logs/prod.log}
set :linked_dirs, %w{var/jwt}

set :symfony_directory_structure, 3
set :sensio_distribution_version, 5

set :format, :pretty
set :log_level, :info
set :keep_releases, 3

set :pm2_config, "current/pm2.json"

# set :permission_method, "acl"
# set :file_permissions_users, ["capistrano"]
# set :file_permissions_groups, ["www-data"]

# namespace :deploy do
#   task :updated do
#     on roles :all do
#       # Create log file with capistrano user to avoid
#       log_file = shared_path.join(fetch(:log_path)).join('prod.log')
#       execute :touch, log_file

#       Rake::Task["symfony:set_permissions"].reenable
#       Rake::Task["deploy:set_permissions:chmod"].reenable
#       invoke "symfony:set_permissions"
#     end
#   end
# end

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
SSHKit.config.command_map[:composer] = "php #{shared_path.join("composer.phar")}"

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
        execute :pm2, 'startOrRestart', fetch(:pm2_config)
      end
    end
  end

  after :starting, 'composer:install_executable'

  after :updated, 'symfony:fix_file_permissions'
  after :updated, 'npm_install'
  after :updated, 'webpack'

  after :finished, 'restart_pm2'

end

# Default branch is :master
# ask :branch, `git rev-parse --abbrev-ref HEAD`.chomp

# Default deploy_to directory is /var/www/my_app_name
# set :deploy_to, '/var/www/my_app_name'

# Default value for :scm is :git
# set :scm, :git

# Default value for :format is :airbrussh.
# set :format, :airbrussh

# You can configure the Airbrussh format using :format_options.
# These are the defaults.
# set :format_options, command_output: true, log_file: 'log/capistrano.log', color: :auto, truncate: :auto

# Default value for :pty is false
# set :pty, true

# Default value for :linked_files is []
# append :linked_files, 'config/database.yml', 'config/secrets.yml'

# Default value for linked_dirs is []
# append :linked_dirs, 'log', 'tmp/pids', 'tmp/cache', 'tmp/sockets', 'public/system'

# Default value for default_env is {}
# set :default_env, { path: "/opt/ruby/bin:$PATH" }

# Default value for keep_releases is 5
# set :keep_releases, 5
