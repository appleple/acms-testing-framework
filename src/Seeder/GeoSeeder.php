<?php

namespace Acms\TestingFramework\Seeder;

use Acms\Services\Facades\Database;

/**
 * geo テーブル（エントリー／ブログ／カテゴリー／ユーザーの位置情報）への Seeder。
 *
 * geo_geometry は MySQL の geometry 型のため、リテラル値ではなく ST_GeomFromText() で
 * 構築する必要がある。SQL ビルダーでは表現できないため raw SQL を直接発行する。
 */
class GeoSeeder extends Seeder
{
    /**
     * geo レコードを 1 件 INSERT する。
     *
     * 紐付け先は geo_eid / geo_uid / geo_bid / geo_cid のいずれかを overrides で指定する。
     * 緯度経度は overrides に geo_lng / geo_lat（デフォルト 135 / 35）として渡せる。
     *
     * @param int $blogId
     * @param array<string, mixed> $data geo_eid / geo_uid / geo_bid / geo_cid / geo_zoom / geo_lat / geo_lng の上書き値
     * @return void
     */
    public static function seed(int $blogId, array $data = []): void
    {
        $dsn = dsn();
        $prefix = $dsn['prefix'];

        $eid = self::getOrFake($data, 'geo_eid', null);
        $uid = self::getOrFake($data, 'geo_uid', null);
        $bid = self::getOrFake($data, 'geo_bid', null);
        $cid = self::getOrFake($data, 'geo_cid', null);
        $zoom = (int) self::getOrFake($data, 'geo_zoom', 10);
        $lng = (float) self::getOrFake($data, 'geo_lng', 135.0);
        $lat = (float) self::getOrFake($data, 'geo_lat', 35.0);

        $table = $prefix . 'geo';
        $sql = "INSERT INTO {$table} (geo_eid, geo_uid, geo_bid, geo_cid, geo_geometry, geo_zoom, geo_blog_id) "
            . 'VALUES (?, ?, ?, ?, ST_GeomFromText(?), ?, ?)';

        Database::query(
            [
                'sql' => $sql,
                'params' => [
                    $eid,
                    $uid,
                    $bid,
                    $cid,
                    sprintf('POINT(%F %F)', $lng, $lat),
                    $zoom,
                    $blogId,
                ],
            ],
            'exec'
        );
    }
}
