<?php

/* 注册表单：定义账号、个人资料和密码字段，并完成基础输入验证。 */

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;


final class RegistrationFormType extends AbstractType
{
    /*
     * buildForm() 决定注册页有哪些输入框。
     * 字段名与 User 属性同名时，Symfony 会自动把表单值写入 User 实体；
     * mapped=false 的字段只用于当前表单，不会直接写入数据库。
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // EmailType 会检查基本邮箱格式；autocomplete 帮助浏览器正确自动填充。
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            ])
            // username 映射到 User::$username，maxlength 与实体字段长度保持一致。
            ->add('username', TextType::class, [
                'label' => 'Nome utente',
                'attr' => ['autocomplete' => 'username', 'maxlength' => 100],
            ])
            // required=false 表示国籍属于可选个人资料，留空也能完成注册。
            ->add('nationality', TextType::class, [
                'label' => 'Nazionalità',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            // 城市同样是可选字段，用于个人资料和好友推荐说明。
            ->add('city', TextType::class, [
                'label' => 'Città',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            // 用户用逗号输入多种语言，User 实体中的辅助方法会在需要时拆分成数组。
            ->add('spokenLanguages', TextType::class, [
                'label' => 'Lingue che parli',
                'help' => 'Separa più lingue con una virgola.',
                'required' => false,
                'attr' => ['placeholder' => 'Italiano, Inglese', 'maxlength' => 255],
            ])
            // 想学习的语言会与其他用户“会说的语言”比较，用于计算好友匹配度。
            ->add('learningLanguages', TextType::class, [
                'label' => 'Lingue che vuoi imparare',
                'help' => 'Serve per trovare competenze complementari.',
                'required' => false,
                'attr' => ['placeholder' => 'Cinese', 'maxlength' => 255],
            ])
            // 兴趣以普通字符串保存，匹配时再按照逗号拆分和比较。
            ->add('interests', TextType::class, [
                'label' => 'Interessi',
                'help' => 'Esempio: musica, cucina, viaggi.',
                'required' => false,
                'attr' => ['placeholder' => 'Musica, cucina, viaggi', 'maxlength' => 500],
            ])
            /*
             * 服务条款复选框不属于 User 数据，所以 mapped=false。
             * IsTrue 强制用户勾选，否则表单不会通过验证。
             */
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Devi accettare le condizioni di utilizzo.'),
                ],
            ])
            /*
             * plainPassword 是用户输入的明文密码，不允许直接保存到实体。
             * RegistrationController 会使用密码哈希器加密后，再写入 User::$password。
             */
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Inserisci una password.'),
                    new Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'La password deve contenere almeno {{ limit }} caratteri.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // 指定表单操作的对象类型，使 Symfony 能在提交时把普通字段映射到 User 实体。
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
