Vagrant.configure("2") do |config|
    # Configure the box to use
    config.vm.box = 'debian/jessie64'

    # Configure the network interfaces
    config.vm.network :private_network, ip: "192.168.33.7"

    # Configure shared folders
    config.vm.synced_folder ".", "/var/www/coopcycle",
        owner: "www-data",
        group: "www-data",
        mount_options: ["dmode=775,fmode=664"]

    # Configure VirtualBox environment
    config.vm.provider :virtualbox do |v|
        v.name = (0...8).map { (65 + rand(26)).chr }.join
        v.customize [ "modifyvm", :id, "--memory", 512 ]
    end

    # Provision the box
    config.vm.provision :ansible do |ansible|
        ansible.extra_vars = { ansible_ssh_user: 'vagrant' }
        ansible.playbook = "ansible/playbook.yml"
    end
end
