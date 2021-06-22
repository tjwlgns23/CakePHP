<?php

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Event\EventInterface;
use Cake\Validation\Validator;
use Cake\ORM\Query;

class ArticlesTable extends Table
{
    public function initialize(array $config) : void
    {
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Tags', ['joinTable' => 'articles_tags', 'dependent' => true]);
    }

    public function beforeSave(EventInterface $event, $entity, $options)
    {
        if($entity->isNew() && !$entity->slug) {
            $sluggedTitle = Text::slug($entity->title);
            $entity->slug = substr($sluggedTitle, 0, 191);
        }

        if ($entity->tag_string) {
            $entity->tags = $this->_buildTags($entity->tag_string);
        }
    }

    public function validationDefault(Validator $validator) : Validator
    {
        $validator
            ->notEmptyString('title')
            ->minLength('title', 1)
            ->maxLength('title', 255)

            ->notEmptyString('body')
            ->minLength('body', 1);
        
        return $validator;
    }

    public function findTagged(Query $query, array $options)
    {
        $columns = [
            'Articles.id', 'Articles.user_id', 'Articles.title',
            'Articles.body', 'Articles.published', 'Articles.created',
            'Articles.slug',
        ];

        $query = $query->select($columns)->distinct($columns);

        if(empty($options['tags'])) {
            $query->leftJoinWith('Tags')->where(['Tags.title In' => null]);
        }else {
            $query->innerJoinWith('Tags')->where(['Tags.title In' => $options['tags']]);
        }

        return $query->group(['Articles.id']);
    }

    protected function _buildTags($tagString)
    {
        $newTags = array_map('trim', explode(',', $tagString));
        $newTags = array_filter($newTags);
        $newTags = array_unique($newTags);
        $out = [];
        $tags = $this->Tags->find()->where(['Tags.title IN' => $newTags])->all();

        foreach ($tags->extract('title') as $existing) {
            $index = array_search($existing, $newTags);
            if ($index !== false) {
                unset($newTags[$index]);
            }
        }
        foreach ($tags as $tag) {
            $out[] = $tag;
        }

        foreach ($newTags as $tag) {
            $out[] = $this->Tags->newEntity(['title' => $tag]);
        }
        
        return $out;
    }
}