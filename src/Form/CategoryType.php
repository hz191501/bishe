<?php

/* 分类表单：定义管理员新增或修改分类时填写的字段。 */

namespace App\Form;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 分类目前只需要一个名称字段；maxlength 与数据库字段长度保持一致。
        $builder->add('name', TextType::class, [
            'label' => 'Nome della categoria',
            'help' => 'Usa un nome breve e facilmente riconoscibile.',
            'attr' => ['placeholder' => 'Esempio: Vita universitaria', 'maxlength' => 100, 'autocomplete' => 'off'],
        ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        // 提交后把 name 写入 Category 实体，新增和编辑页面可以复用同一个表单类。
        $resolver->setDefaults(['data_class' => Category::class]);
    }
}
