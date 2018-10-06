<?php

namespace Application\Sonata\ImageBundle\Admin;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Application\Sonata\ImageBundle\Entity\Image;
use Sonata\CoreBundle\Model\Metadata;

class ImageAdmin extends Admin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        if ($this->hasParentFieldDescription()) { // this Admin is embedded
            // $getter will be something like 'getlogoImage'
            $getter = 'get' . $this->getParentFieldDescription()->getFieldName();

            // get hold of the parent object
            $parent = $this->getParentFieldDescription()->getAdmin()->getSubject();
            if ($parent) {
                $image = $parent->$getter();
            } else {
                $image = null;
            }
        } else {
            $image = $this->getSubject();
        }

        // use $fileFieldOptions so we can add other options to the field
        $fileFieldOptions = array(
            'required' => false,
            'label' => ' ',
            'attr' => ["accept" => "image/gif, image/jpeg, image/png"]
        );
        if ($image) {
            if ($image instanceof Image) {
                $fileName = $image->getFilename();
            }

            // add a 'help' option containing the preview's img tag
            $fileFieldOptions['help'] = $fileName .
                '<img src="' . $image->getWebFilePath() . '" class="admin-preview" />'
                . '<style>img.admin-preview {
                        max-height: 200px;
                        max-width: 200px;
                    }
                    label{}</style>';
        }

        $formMapper
            // ... other fields ...
            ->add('file', 'file', $fileFieldOptions);
    }

    public function prePersist($image)
    {
        $this->manageFileUpload($image);
    }

    public function preUpdate($image)
    {
        $this->manageFileUpload($image);
    }

    private function manageFileUpload($image)
    {
        if ($image->getFile()) {
            $image->refreshUpdated();
        }
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('filename')
            ->add('updated')
            ->add('url', "url", ["attributes" => ["target" => "_blank"]])
            ->add('urlMedium', "string", [
                'template' => '@ApplicationSonataImage/list_image_url.html.twig'
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        // this text filter will be used to retrieve autocomplete fields
        $datagridMapper
            ->add('filename');
    }

    public static function manageAdminClass($admin, $method, $args)
    {
        exit($method);
        if ($method == 'prePersist' || $method == 'preUpdate') {
            $admin->manageEmbeddedImageAdmins($admin, $args[0]);
        }
        call_user_func_array(array($admin, $method), $args);
    }

    public static function manageEmbeddedImageAdmins(Admin $admin, $subject)
    {

        // Cycle through each field
        foreach ($admin->getFormFieldDescriptions() as $fieldName => $fieldDescription) {

            // detect embedded Admins that manage Images
            if ($fieldDescription->getType() === 'sonata_type_admin' &&
                ($associationMapping = $fieldDescription->getAssociationMapping()) &&
                $associationMapping['targetEntity'] === 'Application\Sonata\ImageBundle\Entity\Image'
            ) {
                $getter = 'get' . $fieldName;
                $setter = 'set' . $fieldName;

                /** @var Image $image */
                $image = $subject->$getter();
                if ($image) {
                    if ($image->getFile()) {
                        // update the Image to trigger file management
                        $image->refreshUpdated();
                    } elseif (!$image->getFile() && !$image->getFilename()) {
                        // prevent Sf/Sonata trying to create and persist an empty Image
                        $subject->$setter(null);
                    }
                }
            }
        }
    }


    public function getObjectMetadata($object)
    {
        /** @var Image $object */

        return new Metadata($object->getId(), $object->getUrl(), $object->getUrlMedium());
    }

}