<?php

/* 帖子表单：定义标题、详细内容和分类字段，以及字段验证规则。 */

namespace App\Form;

use App\Entity\BridgeTask;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


final class BridgeTaskType extends AbstractType
{
    // 依次添加帖子标题、正文和分类；字段名会自动对应 BridgeTask 的同名属性。
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 单行标题输入框：页面提示、最大长度和实体中的验证规则相互配合。
            ->add('title', TextType::class, [
                'label' => 'Titolo della richiesta',
                'help' => 'Riassumi il problema in una frase precisa (da 5 a 150 caratteri).',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Esempio: Potete correggere questa email in italiano?',
                    'maxlength' => 150,
                    'autocomplete' => 'off',
                ],
            ])
            // 多行正文输入框：rows 只控制显示高度，maxlength 控制浏览器端最大输入量。
            ->add('description', TextareaType::class, [
                'label' => 'Descrivi la situazione',
                'help' => 'Aggiungi il contesto, ciò che hai già provato e il risultato desiderato.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Spiega con calma cosa ti serve. Più dettagli dai, più sarà facile aiutarti.',
                    'rows' => 9,
                    'maxlength' => 5000,
                ],
            ])
            /*
             * EntityType 会从 Category 数据表读取可选项。
             * choice_label='name' 表示下拉框显示分类名称，提交时保存 Category 对象关系。
             */
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Categoria',
                'placeholder' => 'Scegli la categoria più adatta',
                'help' => 'La categoria permette alle persone giuste di trovare la tua richiesta.',
                'attr' => ['class' => 'form-select form-select-lg'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // 把该表单与 BridgeTask 实体绑定，Symfony 才能自动完成表单与对象之间的数据转换。
        $resolver->setDefaults(['data_class' => BridgeTask::class]);
    }
}
