<?php

/* 个人资料表单：允许用户修改昵称、国家、城市、语言和兴趣。 */

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


final class UserProfileType extends AbstractType
{
    // 本方法声明个人资料编辑页允许修改的字段；没有放入表单的属性不会被用户修改。
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // username 是公开昵称，个人主页、帖子和好友页面都会显示它。
            ->add('username', TextType::class, [
                'label' => 'Nome utente',
                'help' => 'È il nome mostrato alla comunità.',
                'attr' => ['placeholder' => 'Il tuo nome pubblico', 'maxlength' => 100],
            ])
            // 国籍和城市为可选字段，required=false 允许保存空值。
            ->add('nationality', TextType::class, [
                'label' => 'Nazionalità',
                'required' => false,
                'attr' => ['placeholder' => 'Esempio: Italiana', 'maxlength' => 100],
            ])
            ->add('city', TextType::class, [
                'label' => 'Città',
                'required' => false,
                'attr' => ['placeholder' => 'Esempio: Milano', 'maxlength' => 100],
            ])
            /*
             * 页面显示意大利语文字，数据库保存稳定的英文值。
             * 性别是可选资料，代码明确不把它加入好友匹配计算。
             */
            ->add('gender', ChoiceType::class, [
                'label' => 'Genere',
                'required' => false,
                'placeholder' => 'Non specificato',
                'choices' => [
                    'Donna' => 'female',
                    'Uomo' => 'male',
                    'Altro' => 'other',
                    'Preferisco non specificarlo' => 'prefer_not_to_say',
                ],
                'help' => 'Informazione facoltativa; non viene usata per la ricerca o gli abbinamenti.',
            ])
            // 用户填写生日，页面只公开显示自动计算后的年龄。
            ->add('birthDate', DateType::class, [
                'label' => 'Data di nascita',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Facoltativa. Nel profilo viene mostrata soltanto la tua età.',
            ])
            // 以下三个字段用逗号分隔保存，User 的列表辅助方法负责拆分和清理。
            ->add('spokenLanguages', TextType::class, [
                'label' => 'Lingue che parli',
                'help' => 'Separa più lingue con una virgola.',
                'required' => false,
                'attr' => ['placeholder' => 'Italiano, Inglese', 'maxlength' => 255],
            ])
            ->add('learningLanguages', TextType::class, [
                'label' => 'Lingue che vuoi imparare',
                'required' => false,
                'attr' => ['placeholder' => 'Cinese', 'maxlength' => 255],
            ])
            ->add('interests', TextType::class, [
                'label' => 'Interessi',
                'required' => false,
                'attr' => ['placeholder' => 'Musica, cucina, viaggi', 'maxlength' => 500],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // 指定数据对象为 User，让表单提交后更新当前登录用户的个人资料。
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
