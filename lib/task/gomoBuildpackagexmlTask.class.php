<?php

class gomoBuildpackagexmlTask extends sfBaseTask
{
  private
    $gm_options,
    $gm_name;
  
  protected function configure()
  {
    $this->namespace        = 'gomo';
    $this->name             = 'build-package-xml';
    $this->briefDescription = 'make package.xml for plugins';
    $this->detailedDescription = <<<EOF
The [gomo:build-package-xml|INFO] is task to make package.xml for plugins.
Call it with:

  [php symfony gomo:build-package-xml|INFO]
EOF;
    
    $this->initGmArgsAndOptions();
  }
  
  protected function execute($arguments = array(), $options = array())
  {
    $this->gm_name = $arguments['name'];
    
    //load and set global settings
    $file = sfConfig::get('sf_config_dir').DIRECTORY_SEPARATOR.'gm_package_xml_builder_plugin.yml';
    $config = file_exists($file) ? sfYaml::load($file) : array();
    $this->gm_options = $config;
    
    //load and set local settings
    $file = sfConfig::get('sf_plugins_dir').DIRECTORY_SEPARATOR.$this->gm_name.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'builder.yml';
    $config = file_exists($file) ? sfYaml::load($file) : array();
    $this->gm_options = $this->joinGmConfig($this->gm_options, $config);
    
    //set cosole settings
    $array['console'] = $options;
    $this->gm_options = $this->joinGmConfig($this->gm_options, $array);
    
    $xml = $this->getPfm2Xml();
    $this->outputGmXml($xml);
    
    echo PHP_EOL;
  }
  
  /**
   * init console srguments and options
   */
  protected function initGmArgsAndOptions()
  {
    $this->addArgument('name', sfCommandArgument::REQUIRED, 'package name');
    
    $this->addOption('dir', null, sfCommandOption::PARAMETER_REQUIRED, 'package directory', sfConfig::get('sf_plugins_dir'));
    $this->addOption('output', 'o', sfCommandOption::PARAMETER_REQUIRED, 'f=output file, c=only display on console [default:c]');
    $this->addOption('api-version', 'A', sfCommandOption::PARAMETER_REQUIRED, 'api version');
    $this->addOption('release-version', 'R' , sfCommandOption::PARAMETER_REQUIRED, 'release version');
    $this->addOption('api-stability', 'a', sfCommandOption::PARAMETER_REQUIRED, 'Api stability');
    $this->addOption('release-stability', 'r', sfCommandOption::PARAMETER_REQUIRED, 'Release stability');
    $this->addOption('notes', 'N', sfCommandOption::PARAMETER_REQUIRED, 'notes');
  }
  
  /**
   * backup and write package.xml
   *
   * @param string $xml
   */
  private function outputGmXml(&$xml)
  {
    if($this->getGmOption('console.output', 'c') != 'f')
    {
      echo $xml;
      return;
    }
    else
    {
      $file =
        $this->getGmOption('console.dir')
          .DIRECTORY_SEPARATOR
          .$this->gm_name
          .DIRECTORY_SEPARATOR
          .$this->getGmOption('config.file', 'package.xml');
      
      //make bakup file
      if($this->getGmOption('builder.make_backup', true) && file_exists($file))
      {
        $fs = new sfFilesystem($this->dispatcher, $this->formatter);
        if(file_exists($file.'.bak'))
        {
          $fs->remove($file.'.bak');
        }
        $fs->rename($file, $file.'.bak');
      }
      
      //save xml
      $fp = @fopen($file, "w");
      if (!$fp)
      {
        throw new sfFileException('Can\'t open file. '.$file);
      }
      else
      {
        fwrite($fp, $xml);
        fclose($fp);
        chmod($file, 0666);
        $message = $this->formatter->formatSection('file+', $file, null);
        $this->dispatcher->notify(new sfEvent($this, 'command.log', array($message)));
      }
    }
  }
  
  /**
   * @param array &$options original array.
   * @param array &$hight_priority additional array.
   */
  private function joinGmConfig(&$options, &$hight_priority)
  {
    foreach($options as $key=>&$option)
    {
      if(is_array($option))
      {
        $option = $this->joinGmConfig($option, $hight_priority[$key]);
      }
      elseif(!empty($hight_priority[$key]))
      {
        $option = $hight_priority[$key];
      }
      unset($hight_priority[$key]);
    }
    if(!empty($hight_priority))
    {
      $options = array_merge($options, $hight_priority);
    }
    return $options;
  }
  
  /**
   * get option by name from config/GmPluginPackageBuilder/settings.yml
   * or config/GmPluginPackageBuilder/PluginName/settings.yml
   * or console;
   * 
   * @param string $name
   * @param string $default
   * @return mixed
   */
  protected function getGmOption($name, $default = null)
  {
    if($pos = strpos($name, '.'))
    {
      $list = explode('.', $name);
      $option = $this->gm_options;
      foreach($list as &$key)
      {
        //print_r($key);echo '-';print_r($option[$key]);echo PHP_EOL;
        if(!isset($option[$key])) return $default;
        $option = &$option[$key];
      }
      return $option;
    }
    return isset($this->gm_options[$name]) ? $this->gm_options[$name] : $default;
  }
  
  /**
   * if you want add PEAR_PackageFileManager2 options,
   * write here.
   * 
   * @param PEAR_PackageFileManager2 $packagexml
   * @return PEAR_PackageFileManager2 $packagexml
   */
  protected function addPearOptinons($packagexml)
  { 
    return $packagexml;
  }
  
  /**
   * return xml from PEAR_PackageFileManager2
   *
   * @return array
   */
  private function getPfm2Xml()
  {
    set_include_path(get_include_path().PATH_SEPARATOR.$this->getGmOption('builder.pear_path'));
    require_once $this->getGmOption('builder.pear_path','/usr/local/php/lib/php').DIRECTORY_SEPARATOR.'PEAR'.DIRECTORY_SEPARATOR.'PackageFileManager2.php';
    require_once $this->getGmOption('builder.pear_path','/usr/local/php/lib/php').DIRECTORY_SEPARATOR.'PEAR'.DIRECTORY_SEPARATOR.'PackageFileManager'.DIRECTORY_SEPARATOR.'File.php';
    $packagexml = new PEAR_PackageFileManager2;
    PEAR::setErrorHandling(PEAR_ERROR_RETURN);
    
    $options = array(
      'baseinstalldir' => DIRECTORY_SEPARATOR.$this->gm_name,
      'packagedirectory' => $this->getGmOption('console.dir').DIRECTORY_SEPARATOR.$this->gm_name,
      'filelistgenerator' => 'file', // generate from cvs, use file for directory
      'ignore' => array('package.xml.bak', 'builder.yml'),
    );
    
    $options = array_merge($options, $this->getGmOption('config.pear_options', array()));
    
    $e = $packagexml->setOptions($options);
    $packagexml->setPackage($this->gm_name);
    $packagexml->setSummary($this->getGmOption('config.summary', '******************'));
    $packagexml->setDescription($this->getGmOption('config.description','******************'));
    $packagexml->setChannel($this->getGmOption('channel','plugins.symfony-project.org'));
    $packagexml->setAPIVersion($this->getGmOption('console.api-version', '0.0.1'));
    $packagexml->setReleaseVersion($this->getGmOption('console.release-version', '0.0.1'));
    $packagexml->setReleaseStability($this->getGmOption('console.release-stability', 'alpha'));
    $packagexml->setAPIStability($this->getGmOption('console.api-stability', 'alpha'));
    $packagexml->setNotes($this->getGmOption('console.notes','-'));
    $packagexml->setPackageType('php'); // this is a PEAR-style php script package
    $packagexml->setPhpDep($this->getGmOption('config.php.min','5.1.0'));
    $packagexml->setPearinstallerDep($this->getGmOption('config.pearinstaller.min','1.4.1'));
    $packagexml->addPackageDepWithChannel
    (
      'required',
      'symfony',
      $this->getGmOption('config.symfony.channel','pear.symfony-project.com'),
      $this->getGmOption('config.symfony.min','1.1.0'),
      $this->getGmOption('config.symfony.max','1.1.5'),
      false,
      $this->getGmOption('config.symfony.exclude',false)
    );
    
    $packagexml->addMaintainer
    (
      'lead',
      $this->getGmOption('config.name', '***name***'),
      $this->getGmOption('config.user', '***user***'),
      $this->getGmOption('config.email', '***email***')
    );
    $packagexml->setLicense
    (
      $this->getGmOption('config.license', 'MIT License'),
      $this->getGmOption('config.license_uri', null)
    );
    
    //add dependencies
    foreach($this->getGmOption('config.dependencies', array()) as $key=>$dependency)
    {
      $prefix = 'config.dependencies.'.$key.'.';
      switch(true)
      {
        case isset($dependency['channel']):
          $packagexml->addPackageDepWithChannel
          (
            $this->getGmOption($prefix.'type'),
            $this->getGmOption($prefix.'name'),
            $this->getGmOption($prefix.'channel'),
            $this->getGmOption($prefix.'min', false),
            $this->getGmOption($prefix.'max', false),
            $this->getGmOption($prefix.'recommended', false),
            $this->getGmOption($prefix.'exclude', false),
            $this->getGmOption($prefix.'providesextension', false),
            $this->getGmOption($prefix.'nodefault', false)
          );
          break;
          
        case isset($dependency['uri']):
          $packagexml->addPackageDepWithUri
          (
            $this->getGmOption($prefix.'type'),
            $this->getGmOption($prefix.'name'),
            $this->getGmOption($prefix.'uri'),
            $this->getGmOption($prefix.'providesextension', false),
            $this->getGmOption($prefix.'nodefault', false)
          );
          break;
      }
      
    }
    $packagexml->generateContents();
    
    ob_start();
    $error = $this->addPearOptinons($packagexml)->debugPackageFile();
    $output = ob_get_clean();
    $output = htmlspecialchars_decode($output, ENT_QUOTES);
    $output = str_replace('&apos;', '\'', $output);
    
    if($error !== true)
    {
      throw new sfException($error);
    }
    
    //clean obstacle text
    $output = preg_replace('/^Analyzing .+\n/m', '', $output);
    
    return $output;
  }
}