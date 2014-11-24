<?php

namespace devgroup\AdjacencyTree;

use Yii;
use yii\base\Behavior;
use yii\helpers\Url;

/**
 * Tree behavior for ActiveRecord
 * @package devgroup\AdjacencyTree
 * @property \yii\db\ActiveRecord $owner
 */
class Tree extends Behavior
{
    public $idAttribute = 'id';
    public $parentIdAttribute = 'parent_id';

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->owner->hasOne($this->owner->className(), [$this->idAttribute => $this->parentIdAttribute]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->owner->hasMany($this->owner->className(), [$this->parentIdAttribute => $this->idAttribute]);
    }

    /**
     * Helper function - converts 2D-array of rows from db to tree hierarchy for use in Menu widget sorted by parent_id ASC.
     *
     * Attributes needed for use with \yii\widgets\Menu:
     * - name
     * - route or url - if empty, then url attribute of menu item will be unset!
     * - rbac_check _optional_ - will be used to determine if this menu item is allowed to user in rbac
     * - parent_id, id - for hierarchy
     *
     * Optional attributes:
     * - icon
     * - class or css_class
     * - translation_category - if exists and is set then the name will be translated with `Yii::t($item['translation_category'], $item['name'])`
     *
     * @param  array  $rows Array of rows. Example query: `$rows = static::find()->orderBy('parent_id ASC, sort_order ASC')->asArray()->all();`
     * @param  integer $start_index Start index of array to go through
     * @param  integer $current_parent_id ID of current parent
     * @param  boolean $native_menu_mode  Use output for \yii\widgets\Menu
     * @return array Tree suitable for 'items' attribute in Menu widget
     */
    public static function rowsArrayToMenuTree($rows, $start_index = 0, $current_parent_id = 0, $native_menu_mode = true)
    {
        $index = $start_index;
        $tree = [];

        while (isset($rows[$index]) === true && $rows[$index]['parent_id'] <= $current_parent_id) {
            if ($rows[$index]['parent_id'] != $current_parent_id) {
                $index++;
                continue;
            }
            $item = $rows[$index];

            $url = isset($item['route']) ? $item['route'] : $item['url'];

            $tree_item = [
                'label' => $item['name'],
                'url' => preg_match("#^(/|https?://)#Usi", $url) ? $url : ['/'.$url],
            ];
            if (empty($url)) {
                unset($tree_item['url']);
            }

            if (array_key_exists('rbac_check', $item)) {
                $tree_item['visible'] = Yii::$app->user->can($item['rbac_check']);   
            }

            if ($native_menu_mode === false) {
                $attributes_to_check = ['icon', 'class'];
                foreach ($attributes_to_check as $attribute) {
                    if (array_key_exists($attribute, $item)) {
                        $tree_item[$attribute] = $item[$attribute];
                    }
                }
                if (array_key_exists('css_class', $item)) {
                    $tree_item['class']  = $item['css_class'];
                }

                // translate if translation_category is available
                if (array_key_exists('translation_category', $item)) {
                    $tree_item['label'] = Yii::t($item['translation_category'], $item['name']);
                }
            }
            $index++;
            $tree_item['items'] = static::rowsArrayToMenuTree($rows, $index, $item['id'], $native_menu_mode);
            $tree[] = $tree_item;
        }
        return $tree;
    }


}
