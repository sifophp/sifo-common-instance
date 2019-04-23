<?php

namespace Common;

/**
 * Manages the translations.
 */
class I18nTranslatorModel extends \Sifo\Model
{
    /**
     * @param string $language
     * @param $instance
     * @return array
     */
	public function getTranslations( $language, $instance)
	{
        $sql = <<<TRANSLATIONS
        SELECT
         id,
         message,
         comment,
         message_instance,
         SUBSTRING_INDEX(GROUP_CONCAT(destination_instance ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as destination_instance,
         SUBSTRING_INDEX(GROUP_CONCAT(language ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as language,
         SUBSTRING_INDEX(GROUP_CONCAT(translation ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as translation,
         SUBSTRING_INDEX(GROUP_CONCAT(author ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as author,
         SUBSTRING_INDEX(GROUP_CONCAT(modified ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as modified,
         SUBSTRING_INDEX(GROUP_CONCAT(is_base_lang ORDER BY language_depth DESC, instance_depth DESC SEPARATOR '##-##'),'##-##',1) as is_base_lang
        FROM
            (
                SELECT
                    m.id,
                    m.message,
                    m.comment,
                    m.instance AS message_instance,
                    t.instance AS translation_instance,
                    i.instance AS destination_instance,
                    ip.instance AS instance_parent,
                    (
                        SELECT
                            COUNT(ip_x.instance) as depth
                        FROM
                            i18n_instances i_x
                                JOIN i18n_instances ip_x ON i_x.lft BETWEEN ip_x.lft AND ip_x.rgt
                        WHERE
                            i_x.instance = t.instance
                        GROUP BY
                            i_x.instance
                    ) as instance_depth,	
                    t.lang as origin_language,
                    l.lang as language,
                    (
                        SELECT
                            COUNT(lp_x.lang) as depth
                        FROM
                            i18n_languages l_x
                                JOIN i18n_languages lp_x ON l_x.lft BETWEEN lp_x.lft AND lp_x.rgt
                        WHERE
                            l_x.lang = lp.lang
                        GROUP BY
                            l_x.lang
                    ) as language_depth,	
                    t.translation AS translation,
                    t.author AS author,
                    t.modified AS modified,
                    IF(t.lang = l.lang, 1, 0) as is_base_lang
                    
                FROM
                    i18n_messages m
                    LEFT JOIN i18n_languages l ON l.lang = ?
                    LEFT JOIN i18n_languages lp ON l.lft BETWEEN lp.lft AND lp.rgt	
                    LEFT JOIN i18n_instances i ON i.instance = ?
                    LEFT JOIN i18n_instances ip ON i.lft BETWEEN ip.lft AND ip.rgt
                    LEFT JOIN i18n_translations t ON m.id = t.id_message AND t.lang = lp.lang AND t.instance = ip.instance                
                    
                ORDER BY
                    IF(t.id_message IS NULL,0,1), LOWER(CONVERT(m.message USING utf8)), m.id, t.lang = l.lang DESC
        ) tbl
        GROUP BY id
        ORDER BY
        	is_base_lang, modified DESC
TRANSLATIONS;

		return $this->GetArray( $sql, array(
		                                   $language,
		                                   $instance,
		                                   'tag' => 'Get all translations for current language'
		                              ) );
	}

	/**
	 * List of differens languages found in DB.
	 *
	 * @return unknown
	 */
	public function getDifferentLanguages()
	{
		$sql = <<<TRANSLATIONS
SELECT
	*
FROM
	`i18n_language_codes`
WHERE l10n IS NOT NULL
ORDER BY
	english_name ASC
TRANSLATIONS;

		return $this->GetArray( $sql, array( 'tag' => 'List of different languages in DB' ) );
	}

	/**
	 * Get stats of translations.
	 *
	 * @return array
	 */
	public function getStats( $instance, $parent_instance )
	{
		$parent_instance_sql     = '';
		$parent_instance_sub_sql = 'm.instance = ? OR t.instance = ?';
		if ( $parent_instance )
		{
			$parent_instance_sql = ' OR instance IS NULL ';
			$parent_instance_sub_sql = '( m.instance = ? OR m.instance IS NULL ) AND ( t.instance = ? OR t.instance IS NULL )';
		}

		$sql = <<<TRANSLATIONS
SELECT
	l.english_name,
	l.lang,
	lc.local_name AS name,
	@lang 			:= l.lang AS lang,
	@translated 	:= (SELECT COUNT(*) FROM i18n_translations WHERE ( instance = ? $parent_instance_sql ) AND lang = @lang AND translation != '' AND translation IS NOT NULL ) AS total_translated,
	@total 			:=  (SELECT COUNT(DISTINCT(m.id)) FROM i18n_messages m LEFT JOIN i18n_translations t ON m.id=t.id_message AND t.lang = @lang WHERE $parent_instance_sub_sql ) AS total,
	ROUND( ( @translated / @total) * 100, 2 ) AS percent,
	( @total - @translated ) AS missing
FROM
	i18n_languages l
	LEFT JOIN i18n_language_codes lc ON l.lang = lc.l10n
ORDER BY
	percent DESC, english_name ASC
TRANSLATIONS;

		return $this->GetArray( $sql, array(
		                                   'tag' => 'Get current stats',
		                                   $instance,
		                                   $instance,
		                                   $instance,
		                                   $instance,
		                                   $instance
		                              ) );
	}

	/**
	 * Add message in database.
	 * @param $message
	 * @return mixed
	 */
	public function addMessage( $message, $instance = null )
	{
		$sql = <<<SQL
INSERT INTO
	i18n_messages
SET
	message 	= ?,
	instance	= ?
SQL;

		return $this->Execute( $sql, array(
		                                  'tag' => 'Add message',
		                                  $message,
		                                  $instance
		                             ) );
	}

	/**
	 * Add translations for one message in an specific instance.
	 * @param $message
	 * @param $instance
	 * @return mixed
	 */
	public function customizeTranslation( $id_message, $instance )
	{
		$sql = <<<SQL
INSERT INTO
	i18n_translations
SELECT
	?,
	lang,
	'',
	'customize',
	NOW(),
	?
FROM
	i18n_languages;
SQL;

		return $this->Execute( $sql, array(
		                                  'tag' => 'Add message',
		                                  $id_message,
		                                  $instance
		                             ) );
	}

	public function getTranslation( $message, $id_message = null )
	{
		$sql = <<<TRANSLATIONS
SELECT
	id
FROM
	i18n_messages
WHERE
	message = ? OR
	id 		= ?
TRANSLATIONS;

		return $this->getOne( $sql, array(
		                                 'tag' => 'Get correct id message',
		                                 $message,
		                                 $id_message
		                            ) );
	}

	public function getMessageInInhertitance( $message, $instance_inheritance )
	{
		if ( !empty( $instance_inheritance ) )
		{
			$instances = array();
			foreach ( $instance_inheritance as $instance )
			{
				if ( $instance != 'common' )
				{
					$instances[] = "'$instance'";
				}
			}
			$instance_inheritance = implode( ', ', $instances );
		}
		else
		{
			// Is an instance parent.
			return 0;
		}

		$sql = <<<SQL
SELECT
	COUNT(*)
FROM
	i18n_messages
WHERE
	message = ? AND
	( instance IN ( $instance_inheritance ) OR instance IS NULL )
SQL;

		return $this->getOne( $sql, array(
		                                 'tag' => 'Get message in inheritance',
		                                 $message
		                            ) );
	}
}
