<?php

namespace arogachev\sortable\behaviors\numerical;

use yii\db\ActiveRecord;

class ContinuousNumericalSortableBehavior extends BaseNumericalSortableBehavior
{
    /**
     * @var boolean
     */
    protected $_reindexOldModel = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->_intervalSize = 1;

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return array_merge(parent::events(), [
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ]);
    }

    public function beforeUpdate()
    {
        parent::beforeUpdate();

        $this->_reindexOldModel = $this->isScopeChanged() ? true : false;
    }

    public function afterUpdate()
    {
        parent::afterUpdate();

        if (!$this->getSort()) {
            $this->reindexAfterDelete();
        }

        if ($this->_reindexOldModel) {
            $this->_oldModel->reindexAfterDelete(true);
        }

        $this->modelInit();
    }

    public function afterDelete()
    {
        $this->reindexAfterDelete();
    }

    /**
     * @inheritdoc
     */
    public function getSortablePosition()
    {
        return $this->getSort();
    }

    /**
     * @inheritdoc
     */
    public function moveToPosition($position)
    {
        if (parent::moveToPosition($position)) {
            return;
        }

        $currentPosition = $this->getSortablePosition();

        if ($position < $currentPosition) {
            // Moving forward
            $oldSortFrom = $position;
            $oldSortTo = $currentPosition - 1;
            $addedValue = 1;
        } else {
            // Moving back
            $oldSortFrom = $currentPosition + 1;
            $oldSortTo = $position;
            $addedValue = -1;
        }

        $models = $this->query
            ->andWhere(['>=', $this->sortAttribute, $oldSortFrom])
            ->andWhere(['<=', $this->sortAttribute, $oldSortTo])
            ->andWhere(['<>', $this->sortAttribute, $currentPosition])
            ->all();

        foreach ($models as $model) {
            $sort = $model->getSort() + $addedValue;
            $model->updateAttributes([$this->sortAttribute => $sort]);
        }

        $this->model->updateAttributes([$this->sortAttribute => $position]);
    }

    /**
     * @param boolean $useCurrentSort
     */
    public function reindexAfterDelete($useCurrentSort = false)
    {
        $sort = $useCurrentSort ? $this->model->getSort() : $this->_oldModel->getSort();

        $models = $this->query
            ->andWhere(['>', $this->sortAttribute, $sort])
            ->all();

        foreach ($models as $model) {
            $model->updateAttributes([$this->sortAttribute => $sort]);

            $sort++;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getInitialSortByPosition($position)
    {
        return $position;
    }

    /**
     * @inheritdoc
     */
    protected function prependAdded()
    {
        $this->resolveConflict(1, false);
        $this->setSort($this->getInitialSortByPosition(1));
    }
}
