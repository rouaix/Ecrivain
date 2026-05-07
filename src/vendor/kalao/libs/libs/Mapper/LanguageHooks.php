<?php

namespace KS\Mapper;
use Base;

trait LanguageHooks {

    /**
     * Hook on INSERT statements: set current language
     * @param self $self
     */
    static function _beforeinsert($self) {
        if (!$self->lang) {
            $f3=Base::instance();
            $self->lang=$f3->ML()->current;
        }
    }

    /**
     * Hook on INSERT statements: duplicate current item in all languages
     * @param self $self
     * @return bool
     */
    static function _afterinsert($self) {
        $f3=Base::instance();
        if ($self->lang!=$f3->ML()->current)
            return FALSE;
        $id=$self->_id;
        $clone=new $self($self->db);
        foreach ($f3->ML()->languages() as $lang)
            if ($lang!=$self->lang && FALSE===$clone->load(['id=? AND lang=?',$id,$lang])) {
                foreach($self->fields(FALSE) as $key)
                    $clone->set($key,$self->get($key));
                $clone->set('id',$id);
                $clone->set('lang',$lang);
                $clone->save();
            }
        return TRUE;
    }

    /**
     * Hook on UPDATE statements: sync fields in all languages
     * @param self $self
     * @return bool
     */
    static function _afterupdate($self) {
        $f3=Base::instance();
        if (!defined($constant=get_class($self).'::SYNC_FIELDS') || $self->lang!=$f3->ML()->current)
            return FALSE;
        $clone=new $self($self->db);
        $syncFields=$f3->split(constant($constant));
        foreach ($f3->ML()->languages() as $lang)
            if ($lang!=$self->lang) {
                $clone->load(['lang=:lang AND id=:id',[':lang'=>$lang,':id'=>$self->id]]);
                if (!$clone->dry()) {
                    foreach($syncFields as $key)
                        $clone->set($key,$self->get($key));
                    $clone->save();
                }
            }
        return TRUE;
    }

    /**
     * Hook on DELETE statements: delete record in all languages
     * @param self $self
     * @param array $pkeys
     * @return bool
     */
    static function _aftererase($self,$pkeys) {
        $f3=Base::instance();
        if ($self->lang!=$f3->ML()->current)
            return FALSE;
        $self->erase(['id=?',$pkeys['id']]);
        return TRUE;
    }

}