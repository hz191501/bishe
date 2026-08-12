<?php

/* 修改密码表单：接收新密码并检查长度、两次输入是否一致。 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;


final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            /*
             * 当前密码只用于 Controller 中核对用户身份。
             * mapped=false 防止 Symfony 尝试把明文写进 User 实体。
             */
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Password attuale',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Inserisci la password attuale.'),
                ],
            ])
            /*
             * RepeatedType 自动生成两次新密码输入，并检查它们是否一致。
             * 内部的实际输入框类型仍然是 PasswordType。
             */
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Nuova password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Ripeti la nuova password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Le due password non coincidono.',
                // 新密码不能为空，且至少 8 个字符；4096 是防止异常超长输入的安全上限。
                'constraints' => [
                    new NotBlank(message: 'Inserisci una nuova password.'),
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
        // 给该表单使用独立 CSRF 标识，防止其他页面生成的令牌被拿来提交修改密码请求。
        $resolver->setDefaults([
            'csrf_token_id' => 'change_password',
        ]);
    }
}
