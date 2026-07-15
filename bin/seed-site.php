<?php
/**
 * Seed the local/staging site with the approved Japanese information architecture.
 *
 * Run with: wp eval-file bin/seed-site.php
 * Never run against production without a verified backup and release approval.
 */

if (! defined('ABSPATH')) {
    fwrite(STDERR, "WordPress を読み込んで実行してください。\n");
    exit(1);
}

/**
 * Create or update a page by slug.
 *
 * @param array<string,mixed> $page Page configuration.
 */
function jat_seed_page(array $page, int $parentId = 0): int
{
    $existingPages = get_posts(array(
        'post_type'      => 'page',
        'name'           => (string) $page['slug'],
        'post_status'    => array('publish', 'pending', 'draft', 'future', 'private'),
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ));
    $existing = $existingPages[0] ?? null;
    if (
        ! empty($page['preserve_if_published'])
        && $existing instanceof WP_Post
        && $existing->post_status === 'publish'
    ) {
        return (int) $existing->ID;
    }

    $payload  = array(
        'ID'           => $existing instanceof WP_Post ? $existing->ID : 0,
        'post_type'    => 'page',
        'post_name'    => (string) $page['slug'],
        'post_title'   => (string) $page['title'],
        'post_excerpt' => (string) ($page['excerpt'] ?? ''),
        'post_content' => (string) ($page['content'] ?? ''),
        'post_status'  => (string) ($page['status'] ?? 'publish'),
        'post_parent'  => $parentId,
        'menu_order'   => (int) ($page['order'] ?? 0),
    );

    $result = wp_insert_post(wp_slash($payload), true);
    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    return (int) $result;
}

/**
 * Build a structured service page.
 *
 * @param string[] $scenes
 * @param string[] $included
 * @param string[] $steps
 * @param string[] $required
 * @param string[] $risks
 */
function jat_service_content(
    string $summary,
    array $scenes,
    array $included,
    array $steps,
    array $required,
    array $risks
): string {
    $list = static function (array $items): string {
        return '<!-- wp:list --><ul class="wp-block-list">' . implode('', array_map(
            static fn (string $item): string => '<!-- wp:list-item --><li>' . esc_html($item) . '</li><!-- /wp:list-item -->',
            $items
        )) . '</ul><!-- /wp:list -->';
    };

    $cards = static function (array $items): string {
        $html = '<!-- wp:html --><div class="jat-grid jat-grid--3">';
        foreach ($items as $index => $item) {
            $html .= '<div class="jat-card"><span class="jat-card__number">' . esc_html(sprintf('%02d', $index + 1)) . '</span><h3>' . esc_html($item) . '</h3></div>';
        }
        return $html . '</div><!-- /wp:html -->';
    };

    $timeline = '<!-- wp:html --><div class="jat-feature-list">';
    foreach ($steps as $index => $step) {
        $timeline .= '<div class="jat-feature"><span class="jat-feature__mark">' . esc_html((string) ($index + 1)) . '</span><div><h3>' . esc_html($step) . '</h3></div></div>';
    }
    $timeline .= '</div><!-- /wp:html -->';

    return '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">' . esc_html($summary) . '</p><!-- /wp:paragraph -->'
        . '<!-- wp:heading --><h2 class="wp-block-heading">このような場面に</h2><!-- /wp:heading -->'
        . $cards($scenes)
        . '<!-- wp:heading --><h2 class="wp-block-heading">標準サービスに含まれる内容</h2><!-- /wp:heading -->'
        . $list($included)
        . '<!-- wp:heading --><h2 class="wp-block-heading">サービスの流れ</h2><!-- /wp:heading -->'
        . $timeline
        . '<!-- wp:heading --><h2 class="wp-block-heading">お申し込み時に必要な情報</h2><!-- /wp:heading -->'
        . $list($required)
        . '<!-- wp:heading --><h2 class="wp-block-heading">変更・未会合などへの対応</h2><!-- /wp:heading -->'
        . $list($risks)
        . '<!-- wp:paragraph --><p>追加の現地支援、立替、交通費、待機延長、特殊なサインボード制作などは、内容を確認して個別にご案内します。オンライン申込の送信時点では予約確定ではありません。</p><!-- /wp:paragraph -->';
}

/**
 * Build a location page whose operational facts remain editable and reviewable.
 *
 * @param string[] $zones
 * @param string[] $meetMethods
 * @param string[] $notes
 */
function jat_location_content(string $summary, array $zones, array $meetMethods, array $notes): string
{
    $list = static function (array $items): string {
        return '<!-- wp:list --><ul class="wp-block-list">' . implode('', array_map(
            static fn (string $item): string => '<!-- wp:list-item --><li>' . esc_html($item) . '</li><!-- /wp:list-item -->',
            $items
        )) . '</ul><!-- /wp:list -->';
    };

    return '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">' . esc_html($summary) . '</p><!-- /wp:paragraph -->'
        . '<!-- wp:heading --><h2 class="wp-block-heading">主な対応区域</h2><!-- /wp:heading -->' . $list($zones)
        . '<!-- wp:heading --><h2 class="wp-block-heading">会合方法</h2><!-- /wp:heading -->' . $list($meetMethods)
        . '<!-- wp:heading --><h2 class="wp-block-heading">車両・次の交通手段へのご案内</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>事前に乗務員名、連絡先、車種、車番、配車場所を確認し、お客様との会合後に情報を照合してご案内します。施設運用や当日の混雑状況により、会合位置や車両への動線をご相談する場合があります。</p><!-- /wp:paragraph -->'
        . '<!-- wp:heading --><h2 class="wp-block-heading">お申し込み前にご確認ください</h2><!-- /wp:heading -->' . $list($notes)
        . '<!-- wp:paragraph {"fontSize":"sm"} --><p class="has-sm-font-size">ターミナル、改札、車寄、工事情報などは変更される場合があります。最終的な会合場所は、予約確定時またはサービス前のご案内でお知らせします。</p><!-- /wp:paragraph -->';
}

$serviceIndex = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">空港・主要駅でのお迎えとお見送りを、事前確認から会合、車両・交通手段へのご案内まで一貫して支援します。グリーター、手配済みの車両・乗務員、ご依頼元の情報をつなぎ、移動開始までの不確実さを減らします。</p><!-- /wp:paragraph -->
<!-- wp:html --><div class="jat-grid jat-grid--2">
<article class="jat-card"><span class="jat-card__number">01</span><h3>空港お迎え</h3><p>到着情報を確認し、到着ロビーでサインボードを掲げてお迎えします。</p><a class="jat-card__link" href="/service/airport-meet/" aria-label="空港お迎えの詳細"></a></article>
<article class="jat-card"><span class="jat-card__number">02</span><h3>空港お見送り</h3><p>車寄せでのお迎えから、チェックイン、保安検査場入口までをご案内します。</p><a class="jat-card__link" href="/service/airport-sending/" aria-label="空港お見送りの詳細"></a></article>
<article class="jat-card"><span class="jat-card__number">03</span><h3>駅お迎え</h3><p>到着ホームや改札付近でお迎えし、車両への移動を支援します。</p><a class="jat-card__link" href="/service/station-meet/" aria-label="駅お迎えの詳細"></a></article>
<article class="jat-card"><span class="jat-card__number">04</span><h3>駅お見送り</h3><p>車寄せでのお迎えから、改札・ホーム・乗車までをご希望の範囲で支援します。</p><a class="jat-card__link" href="/service/station-sending/" aria-label="駅お見送りの詳細"></a></article>
</div><!-- /wp:html -->
<!-- wp:heading --><h2 class="wp-block-heading">個別相談サービス</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>空港内の乗継、複数地点での同行、団体・複数便など、標準サービスに含まれない内容もご相談いただけます。お客様またはご依頼元が手配したハイヤー・タクシーとの連携を含め、対応可否と料金は行程を確認してご案内します。</p><!-- /wp:paragraph -->
HTML;

$areaIndex = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">羽田空港、成田空港、東京駅、品川駅をコア対応地点として、到着・出発時の会合と移動を支援します。</p><!-- /wp:paragraph -->
<!-- wp:html --><div class="jat-grid jat-grid--4">
<article class="jat-location"><span class="jat-location__code">HND</span><h3>羽田空港</h3><p>第1・第2・第3ターミナル</p><a class="jat-card__link" href="/area/haneda-airport/" aria-label="羽田空港の詳細"></a></article>
<article class="jat-location"><span class="jat-location__code">NRT</span><h3>成田空港</h3><p>第1・第2・第3ターミナル</p><a class="jat-card__link" href="/area/narita-airport/" aria-label="成田空港の詳細"></a></article>
<article class="jat-location"><span class="jat-location__code">TYO</span><h3>東京駅</h3><p>新幹線ホーム・主要改札・車寄せ</p><a class="jat-card__link" href="/area/tokyo-station/" aria-label="東京駅の詳細"></a></article>
<article class="jat-location"><span class="jat-location__code">SGW</span><h3>品川駅</h3><p>新幹線ホーム・改札・車寄せ</p><a class="jat-card__link" href="/area/shinagawa-station/" aria-label="品川駅の詳細"></a></article>
</div><!-- /wp:html -->
<!-- wp:heading --><h2 class="wp-block-heading">日本国内・アジアのその他の空港・駅</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>コア対応地点以外の日本国内およびアジアの空港・駅は、日時、人数、行程、必要な支援内容を伺い、ネットワークを確認したうえで対応可否を個別にご案内します。すべての地域での対応を保証するものではありません。</p><!-- /wp:paragraph -->
HTML;

$flow = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">オンラインでのお申し込みから、担当者による内容確認、グリーターと手配済み車両・乗務員の連携、当日の会合、完了報告までの標準的な流れです。</p><!-- /wp:paragraph -->
<!-- wp:html --><div class="jat-feature-list">
<div class="jat-feature"><span class="jat-feature__mark">1</span><div><h3>オンラインでお申し込み</h3><p>サービス、日時、行程、旅客、サインボード、車両などをご入力ください。</p></div></div>
<div class="jat-feature"><span class="jat-feature__mark">2</span><div><h3>担当者が内容を確認</h3><p>対応人員、場所、追加確認事項、料金を確認します。送信時点では予約確定ではありません。</p></div></div>
<div class="jat-feature"><span class="jat-feature__mark">3</span><div><h3>お見積り・予約確定</h3><p>必要な確認が終わり、お見積りと条件をメール等の書面で双方が確認した後、予約確定をご連絡します。</p></div></div>
<div class="jat-feature"><span class="jat-feature__mark">4</span><div><h3>サービス前の準備</h3><p>フライト・列車、連絡先、サインボード、車両・乗務員情報を整理し、会合位置と連携手順を確認します。</p></div></div>
<div class="jat-feature"><span class="jat-feature__mark">5</span><div><h3>現地で会合・車両へご案内</h3><p>指定地点でお迎えし、ご本人確認後、乗務員と連携して約定した車両または終了地点までご案内します。遅延や未会合などの異常時は、ご依頼元へ状況を連絡して対応を確認します。</p></div></div>
<div class="jat-feature"><span class="jat-feature__mark">6</span><div><h3>サービス完了・ご報告</h3><p>定めた終了地点までのご案内後、事前に合意した報告先と項目に沿って完了を報告します。</p></div></div>
</div><!-- /wp:html -->
<!-- wp:heading --><h2 class="wp-block-heading">お急ぎのご依頼</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>サービス日時が近い場合でも、まず行程をお知らせください。対応可否を優先して確認します。オンライン送信だけでは予約確定になりません。</p><!-- /wp:paragraph -->
HTML;

$price = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">料金はサービス種別、場所、日時、人数、必要な支援内容などを確認して個別にご案内します。各項目を分けたお見積りを提示し、合計金額と条件を書面で確認します。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">基本サービス料金（目安）</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>標準的な対応範囲（スタッフ1名、2時間まで）における基本料金の目安です。料金はすべて<strong>消費税込み</strong>です。</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>羽田空港（HND）</h3><!-- /wp:heading -->
<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} --><figure class="wp-block-table is-style-stripes"><table><thead><tr><th>サービス内容</th><th>基本料金（税込）</th><th>早朝・深夜料金（税込）</th><th>基本時間</th></tr></thead><tbody>
<tr><td>空港内の送迎サポート</td><td>7,000円</td><td>12,000円</td><td>2時間以内</td></tr>
<tr><td>ターミナル間の乗継サポート</td><td>12,000円</td><td>17,000円</td><td>3時間以内</td></tr>
<tr><td>空港と東京都内の駅・ホテル間の同行</td><td>15,000円</td><td>20,000円</td><td>3時間以内</td></tr>
</tbody></table></figure><!-- /wp:table -->

<!-- wp:heading {"level":3} --><h3>東京駅（Tokyo Station）</h3><!-- /wp:heading -->
<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} --><figure class="wp-block-table is-style-stripes"><table><thead><tr><th>サービス内容</th><th>基本料金（税込）</th><th>早朝・深夜料金（税込）</th><th>基本時間</th></tr></thead><tbody>
<tr><td>駅構内の送迎サポート</td><td>7,000円</td><td>12,000円</td><td>1時間30分以内</td></tr>
<tr><td>駅と近隣ホテル間の同行</td><td>13,000円</td><td>18,000円</td><td>3時間以内</td></tr>
</tbody></table></figure><!-- /wp:table -->

<!-- wp:heading {"level":3} --><h3>品川駅（Shinagawa Station）</h3><!-- /wp:heading -->
<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} --><figure class="wp-block-table is-style-stripes"><table><thead><tr><th>サービス内容</th><th>基本料金（税込）</th><th>早朝・深夜料金（税込）</th><th>基本時間</th></tr></thead><tbody>
<tr><td>駅構内の送迎サポート</td><td>7,000円</td><td>12,000円</td><td>1時間30分以内</td></tr>
<tr><td>駅と近隣ホテル間の同行</td><td>13,000円</td><td>18,000円</td><td>3時間以内</td></tr>
</tbody></table></figure><!-- /wp:table -->

<!-- wp:heading {"level":3} --><h3>成田空港（NRT）</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>対応エリア拡大に向け、現在<strong>準備中</strong>です。</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>共通条件</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>時間帯：</strong> 基本時間帯は <code>7:00〜20:59</code> です。到着時刻またはご指定の待命時刻が <code>21:00〜翌6:59</code> の場合、早朝・深夜料金が適用されます。</li>
<li><strong>延長料金：</strong> 規定の基本時間を超える場合、30分ごとに <code>3,000円（税込）</code> の延長料金が発生します。時間の起算点は、フライト到着時刻またはお客様が指定された待命時刻となります。</li>
<li><strong>実費負担：</strong> 同行サービス中に発生する交通費等の実費は、別途申し受けます。</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2 class="wp-block-heading">お見積りの主な構成</h2><!-- /wp:heading -->
<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} --><figure class="wp-block-table is-style-stripes"><table><thead><tr><th>項目</th><th>確認内容</th></tr></thead><tbody>
<tr><td>基本サービス料金</td><td>空港・駅、お迎え・お見送り、標準対応範囲</td></tr>
<tr><td>日時・時期による変動</td><td>早朝・深夜、繁忙時期、直前のご依頼</td></tr>
<tr><td>人数・行程による変動</td><td>団体、複数便、複数地点、追加スタッフの要否</td></tr>
<tr><td>追加支援料金</td><td>標準範囲を超える待機、同行、特殊なサインボードなど</td></tr>
<tr><td>実費・その他</td><td>交通、施設利用、手配品など事前に合意した費用</td></tr>
</tbody></table></figure><!-- /wp:table -->
<!-- wp:heading --><h2 class="wp-block-heading">料金確定まで</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>お申し込み内容を受け付けた後、担当者が確認し、項目別のお見積りまたは追加質問をご連絡します。合計金額、追加料金が発生し得る条件、変更・キャンセル条件に同意いただき、当社からメール等の書面で予約確定をお知らせするまでは、サービスの成立や料金の請求は確定しません。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">変更・キャンセル</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>変更・キャンセルの取扱いは、確定した予約条件とキャンセルポリシーに基づきます。正式な条件は、事業責任者および法務確認後に公開します。</p><!-- /wp:paragraph -->
HTML;

$faqItems = array(
    '予約・お申し込み' => array(
        'オンライン送信で予約は確定しますか。' => 'いいえ。送信時点では受付です。担当者が内容、人員、場所、料金を確認し、予約確定をご連絡します。',
        'いつまでに申し込めばよいですか。' => '行程がお決まりになり次第、早めにお申し込みください。受付期限はサービス内容や場所により異なるため、日時が近い場合は個別に確認します。',
        '直前の依頼はできますか。' => '対応人員と現地状況を確認します。オンライン送信だけでは確定しないため、お急ぎの場合はお問い合わせください。',
        '複数便や団体も申し込めますか。' => '可能です。便ごとの到着時刻、代表者、人数、車両、集合方法を整理して確認します。',
    ),
    'サービス当日' => array(
        'フライトが早着・遅延した場合はどうなりますか。' => '当日の到着情報を確認しながら準備します。大幅な変更や別便への変更など、追加対応が必要な場合は依頼元へ確認します。',
        '列車が遅延・運休した場合はどうなりますか。' => '運行情報とご連絡をもとに対応方法を確認します。改札やホーム変更がある場合は、最新の情報に合わせて会合方法を調整します。',
        'お客様と会えない場合はどうしますか。' => '依頼元や緊急連絡先へ状況を報告し、施設の案内窓口への相談や到着状況の確認などを行います。',
        'どこまで同行してもらえますか。' => 'サービス種別と予約内容により異なります。車両、チェックインカウンター、保安検査場入口、改札、ホームなど、約定した終了地点までご案内します。',
    ),
    'サインボード・旅客情報' => array(
        'サインボードには何を表示できますか。' => 'お客様名、会社名、グループ名などをご指定いただけます。特殊なレイアウトは対応可否をご案内します。',
        'ロゴを入れられますか。' => '初期のオンライン申込ではロゴファイルを受け付けません。必要な場合は申込後に個別にご相談ください。',
        '複数名の名前を表示できますか。' => '文字数と見やすさを確認してご案内します。団体名や代表者名の表示もご相談いただけます。',
        'どの旅客情報が必要ですか。' => 'お名前、人数、到着・出発情報、当日の連絡先、サインボード表示、必要な支援内容などです。',
    ),
    '料金・変更' => array(
        '料金には何が含まれますか。' => '標準サービスの範囲と追加項目を確認し、お見積り時に明示します。未確認の金額や条件をサイト上で自動確定しません。',
        '交通費や立替費用はどうなりますか。' => '必要性、金額、精算方法を事前に確認します。合意のない立替を前提としたサービスではありません。',
        '予約内容を変更できますか。' => '変更内容と時期を確認し、対応可否と追加条件をご案内します。変更が確定するまでは元の予約条件が基準です。',
        'キャンセルできますか。' => '可能です。確定した予約条件と公開済みのキャンセルポリシーに基づきます。正式条件は予約確定前にご確認いただきます。',
    ),
    '法人・特殊なご相談' => array(
        '法人契約や継続利用はできますか。' => '利用頻度、発注方法、請求、報告形式などを伺い、個別にご案内します。',
        '月次請求はできますか。' => '契約条件と審査を含めて個別に確認します。サイト上で無条件にはお約束していません。',
        '車両会社との連携はできますか。' => '乗務員名、連絡先、車種、車番、配車場所を事前に確認し、会合後の車両案内を支援します。',
        '完了報告はありますか。' => '報告先、必要項目、送付方法を事前に確認します。標準内容は運用確定後に公開します。',
        '外国語での対応はできますか。' => '必要言語、日時、場所を伺い、対応可能な人員を確認します。未確認の言語を標準対応としては表示していません。',
        '車いす利用者の支援はできますか。' => '必要な支援範囲、同行者、施設・航空会社・鉄道会社側の手配状況を確認し、安全に対応できる範囲をご案内します。',
        '手荷物が多い場合も対応できますか。' => '個数、サイズ、旅客人数、車両容量、施設側のポーター手配などを確認します。',
        '両替、通信、交通カードの購入を手伝えますか。' => '追加の現地支援にあたるため、希望内容と時間を伺い、対応可否をご案内します。',
    ),
);

$faq = '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">お申し込み、当日対応、料金、変更、法人利用など、よくいただくご質問をまとめました。</p><!-- /wp:paragraph --><!-- wp:shortcode -->[jat_faq_list]<!-- /wp:shortcode -->';

$corporate = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">企業来賓、国際会議、視察団、研修旅行、旅行会社・車両会社との連携など、複数の関係者が関わる移動を整理して支援します。</p><!-- /wp:paragraph -->
<!-- wp:html --><div class="jat-grid jat-grid--2">
<div class="jat-card"><h3>複数便・複数名</h3><p>便ごとの到着時刻、代表者、車両、会合方法を整理し、必要な人数と進行を確認します。</p></div>
<div class="jat-card"><h3>車両会社との連携</h3><p>乗務員、車種、車番、配車場所を照合し、会合後の移動を支援します。</p></div>
<div class="jat-card"><h3>継続利用</h3><p>会社コード、担当窓口、発注方法、請求条件など、ご利用規模に応じた運用をご相談いただけます。</p></div>
<div class="jat-card"><h3>報告方法</h3><p>報告先、必要項目、送付時期を伺い、ご希望の運用を確認します。</p></div>
</div><!-- /wp:html -->
<!-- wp:heading --><h2 class="wp-block-heading">完了報告の項目例</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>以下は報告内容を検討するための例であり、実際の報告項目、方法、送付時期はご依頼ごとの約定により決まります。</p><!-- /wp:paragraph -->
<!-- wp:list --><ul class="wp-block-list"><li>お客様との会合時刻</li><li>手配済み車両・乗務員との連携時刻</li><li>ご案内を完了した地点と時刻</li><li>遅延、会合位置の変更、未会合対応などの特記事項</li></ul><!-- /wp:list -->
<!-- wp:heading --><h2 class="wp-block-heading">ご相談時にお知らせいただきたい内容</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>利用予定の空港・駅、日時、便・列車</li><li>旅客人数、便数、車両台数</li><li>必要な言語や現地支援</li><li>依頼・連絡・完了報告の窓口</li><li>継続利用の場合は想定件数と請求条件</li></ul><!-- /wp:list -->
HTML;

$company = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">私たちは、空港・駅での会合に伴う不確実さを減らし、お客様と次の移動を確実につなぐことを目指しています。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">私たちの考え方</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>迎送サービスは、サインボードを掲げるだけでは完了しません。到着情報、旅客情報、車両情報、会合位置、終了地点を事前に整理し、変化が起きたときにも関係者へ正確に伝えることが重要です。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">会社概要</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>法定会社名、所在地、代表者、設立日、資本金、連絡先、営業時間、許認可などは、登記・契約情報との照合が完了した後に掲載します。このページは確認完了まで公開対象外です。</p><!-- /wp:paragraph -->
HTML;

$contact = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">サービス内容、法人契約、複数便・団体、標準外の場所や支援についてご相談いただけます。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">お問い合わせ前に</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>サービス希望日とおおよその時刻</li><li>空港・駅と到着または出発</li><li>旅客人数、便名・列車名</li><li>希望する支援内容</li><li>ご連絡先と連絡しやすい時間</li></ul><!-- /wp:list -->
<!-- wp:paragraph --><p>公開連絡先と受付時間の確認が完了するまで、このページは公開対象外です。標準のご依頼はオンライン申込をご利用ください。</p><!-- /wp:paragraph -->
HTML;

$cases = <<<'HTML'
<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">お客様の許可を得た事例のみ、個人や取引先を特定できない形でご紹介します。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">掲載方針</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>事例は「ご依頼の背景」「当日の課題」「実施内容」「結果」の順で整理します。実在の顧客名、旅客名、便、車両、連絡先などは、明確な許可がない限り掲載しません。</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>現在、公開許諾済みの事例を準備しています。</p><!-- /wp:paragraph -->
HTML;

$legalDraft = static function (string $name): string {
    return '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">' . esc_html($name) . 'は、事業責任者および法務確認後に公開します。</p><!-- /wp:paragraph -->'
        . '<!-- wp:heading --><h2 class="wp-block-heading">公開前の確認事項</h2><!-- /wp:heading -->'
        . '<!-- wp:list --><ul class="wp-block-list"><li>実際の会社・サービス・決済・取消条件との一致</li><li>個人情報の取得目的、保管期間、委託先、開示・削除手続き</li><li>お問い合わせ窓口と制定・改定日</li><li>日本語母語話者および必要な専門家による確認</li></ul><!-- /wp:list -->';
};

$recruit = <<<'HTML'
<!-- wp:heading --><h2 class="wp-block-heading">募集職種・業務内容</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>羽田空港や東京駅をはじめとする主要な玄関口にて、インバウンドVIPのお客様を最初にお迎えし、車両への誘導や手荷物配送の手配など、ご移動の全行程をシームレスにサポートする「ミート＆グリート」業務を担っていただきます。</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">求める人物像</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>ホスピタリティ重視の方：お客様に寄り添った丁寧なコミュニケーションができる方。（日本語のみでも可）</li><!-- /wp:list-item --><!-- wp:list-item --><li>英語・多言語スキル：日常会話レベルの語学力（英語、中国語など）をお持ちの方は歓迎いたします。</li><!-- /wp:list-item --><!-- wp:list-item --><li>業界経験者優遇：空港業務、旅行添乗員、ハイヤー運転手、ホテルコンシェルジュなどの経験がある方。</li><!-- /wp:list-item --><!-- wp:list-item --><li>プロフェッショナルな意識：守秘義務を遵守し、常に身だしなみや立ち居振る舞いに気配りのできる方。</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- wp:heading --><h2 class="wp-block-heading">暫定募集条件（※正式公開前に要確認）</h2><!-- /wp:heading -->
<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes jat-recruit-conditions"} --><figure class="wp-block-table is-style-stripes jat-recruit-conditions"><table><tbody>
<tr><td><strong>勤務地</strong></td><td>東京国際羽田空港、東京都内駅周辺エリア</td></tr>
<tr><td><strong>勤務時間</strong></td><td>シフト制（フライト・列車スケジュールに基づく変動あり）<br>※週1日から、または1日1シフトからのスポット勤務も相談可能</td></tr>
<tr><td><strong>一般アルバイト給与</strong></td><td>基本時給 1,350～1,500円〜（経験・能力を考慮し決定）<br>①日勤帯（07:00-20:59）単価 1,500円<br>②深夜帯（21:00-06:59）単価 1,800円<br>※1シフト＝2時間単位から選択可能</td></tr>
<tr><td><strong>シフトリーダー固定給</strong></td><td>A：早朝（04:00〜11:00） 17,500円<br>B：日勤（10:30〜17:30） 14,000円<br>C：夜勤（17:00〜24:00） 14,000円<br>※3シフト時間帯より選択 / 7時間（休憩1時間）</td></tr>
<tr><td><strong>応募資格（一般）</strong></td><td>・日本語可、未経験可、学歴・経験不問<br>・基本的なパソコンスキル / 携帯電話でメールができる方<br>・スーツケースなどの積み込み作業ができる方（10kg～25kg）<br>・外国籍の方歓迎（N2レベル以上必須）</td></tr>
<tr><td><strong>応募資格（リーダー）</strong></td><td>・日本語および英語＜必須＞<br>・実際のミート実務およびシフト内の一般アルバイトの調整やレポートなどの事務作業を担当</td></tr>
<tr><td><strong>待遇・福利厚生</strong></td><td>・交通費全額支給（東京都外など上限あり、要事前相談）<br>・制服貸与なし（個人の上下黒スーツ着用）<br>・研修制度あり（接客マナーなど実務研修）<br>・雇用保険、労災保険、健康保険、厚生年金（※適用条件あり）</td></tr>
<tr><td><strong>試用期間</strong></td><td>3か月</td></tr>
</tbody></table></figure><!-- /wp:table -->
<!-- wp:heading --><h2 class="wp-block-heading">応募方法</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>まずは本サイトのお問い合わせフォームより、ご希望の職種と勤務スタイルを添えてご連絡ください。選考の上、面接日程をご案内いたします。</p><!-- /wp:paragraph -->
HTML;

$pages = array(
    array('key' => 'home', 'slug' => 'home', 'title' => 'ホーム', 'excerpt' => '空港・駅でのお出迎えを、確実でスムーズに。', 'content' => '', 'order' => 0),
    array('key' => 'service', 'slug' => 'service', 'title' => 'サービス', 'excerpt' => '空港・駅のお迎え・お見送りサービス', 'content' => $serviceIndex, 'order' => 10),
    array('key' => 'airport-meet', 'parent' => 'service', 'slug' => 'airport-meet', 'title' => '空港お迎え', 'excerpt' => '到着情報を確認し、到着ロビーでサインボードを掲げてお迎えします。', 'content' => jat_service_content('到着情報と車両情報を事前に確認し、到着ロビーでサインボードを掲げてお迎えします。会合後はご本人・人数・目的地を確認し、予約内容に沿って車両または次の交通手段へご案内します。', array('海外からの来賓・取引先のお迎え', '初めて日本を訪れるお客様', '旅行団体・研修・国際会議', '乗務員が到着ロビーへ入れない配車'), array('到着便情報の確認', '到着ロビーでのサインボード待機', 'お名前・人数・目的地の確認', '事前登録した乗務員・車両情報との照合', '約定した車両または交通手段へのご案内'), array('お申し込み内容の確認', '到着情報と車両情報の事前照合', '指定位置での待機とお迎え', 'ご本人確認と必要事項の確認', '車両または終了地点までのご案内'), array('便名、到着予定日時、ターミナル', '旅客名、人数、当日の連絡先', 'サインボード表示', '目的地と手荷物のおおよその量', '乗務員名、連絡先、車種、車番、配車場所'), array('早着・遅延・ターミナル変更を確認し、必要に応じて依頼元へ連絡します。', '未会合時は連絡先と到着状況を確認し、案内窓口への相談など定めた手順で対応します。', '長時間待機や別便への変更は、追加対応の可否と条件を確認します。')), 'order' => 11),
    array('key' => 'airport-sending', 'parent' => 'service', 'slug' => 'airport-sending', 'title' => '空港お見送り', 'excerpt' => '車寄せでのお迎えから、チェックイン、保安検査場入口までをご案内します。', 'content' => jat_service_content('空港の車寄せなど約束した場所でお迎えし、ご本人・便・手荷物を確認します。チェックインカウンターや施設をご案内し、予約内容に沿って保安検査場入口などの終了地点までお見送りします。', array('海外へ出発する来賓・取引先', '空港手続きに不慣れなお客様', '団体・研修・会議参加者の出発', '車両到着から空港内動線をつなぐ場面'), array('出発便とカウンター情報の確認', '車寄せなど指定場所でのお迎え', 'ご本人・便・手荷物の確認', 'チェックインカウンターへのご案内', '約定した終了地点までのお見送り'), array('お申し込み内容の確認', '乗務員・到着時刻の事前確認', '車寄せなどで会合', 'チェックインと空港内移動の支援', '保安検査場入口などでサービス完了'), array('便名、出発日時、ターミナル', '旅客名、人数、当日の連絡先', '手荷物のおおよその量', '車両の到着時刻、乗務員、車番', '希望する終了地点と追加支援'), array('配車遅延やカウンター変更がある場合は、最新情報を確認して依頼元へ報告します。', '航空会社が提供する手続き、手荷物規定、優先サービスの可否は航空会社の判断に従います。', 'ポーター、立替、特殊な支援は事前確認が必要です。')), 'order' => 12),
    array('key' => 'station-meet', 'parent' => 'service', 'slug' => 'station-meet', 'title' => '駅お迎え', 'excerpt' => '到着ホームや改札付近でお迎えし、車両への移動を支援します。', 'content' => jat_service_content('列車の到着時刻、号車、座席、改札・ホーム情報を確認し、サインボードでお迎えします。会合後は予約内容に沿って車両や次の交通手段へご案内します。', array('新幹線で到着する来賓・取引先', '大きな駅の移動に不慣れなお客様', '団体・研修の駅到着', '乗務員がホームや改札へ入れない配車'), array('列車・到着時刻・号車情報の確認', '指定ホームまたは改札付近での待機', 'サインボードによるお迎え', 'お名前・人数・目的地の確認', '車寄せまたは約定した終了地点へのご案内'), array('お申し込み内容の確認', '列車・号車・座席と車両情報の事前照合', '指定位置で待機', '会合とご本人確認', '駅構内移動と車両へのご案内'), array('列車名、列車番号、到着日時', '号車、座席、利用予定の改札', '旅客名、人数、当日の連絡先', 'サインボード表示', '乗務員・車両・配車場所'), array('遅延、運休、乗車列車の変更がある場合は、最新情報とご連絡をもとに対応を確認します。', '工事、混雑、入場制限により、ホームでの会合が難しい場合は改札など別の位置をご案内します。', '未会合時は依頼元への報告と施設情報の確認を行います。')), 'order' => 13),
    array('key' => 'station-sending', 'parent' => 'service', 'slug' => 'station-sending', 'title' => '駅お見送り', 'excerpt' => '車寄せでのお迎えから、改札・ホーム・乗車までをご希望の範囲で支援します。', 'content' => jat_service_content('駅の車寄せなど指定場所でお迎えし、ご本人・列車・乗車券状況を確認します。予約内容に沿って改札、ホーム、号車付近までご案内し、定めた地点でサービスを完了します。', array('新幹線で出発する来賓・取引先', '駅構内の移動に不慣れなお客様', '団体・研修の出発管理', '車両から列車までの移動をつなぐ場面'), array('出発列車・ホーム情報の確認', '車寄せなど指定場所でのお迎え', 'ご本人・人数・列車・手荷物の確認', '改札・ホームへのご案内', '約定した終了地点までのお見送り'), array('お申し込み内容の確認', '乗務員・到着時刻と列車情報の確認', '車寄せなどで会合', '改札・ホーム・号車への移動支援', '約定した地点でサービス完了'), array('列車名、列車番号、出発日時', '号車、座席、乗車券の手配状況', '旅客名、人数、当日の連絡先', '手荷物のおおよその量', '車両到着時刻、乗務員、車番'), array('列車変更や遅延時は、新しい情報と乗車券状況を確認して対応方法をご相談します。', '乗車券の購入・変更、立替、ポーターなどは標準サービスに含めず、事前確認が必要です。', '混雑や入場制限により、希望する終了地点まで同行できない場合があります。')), 'order' => 14),
    array('key' => 'transfer-support', 'parent' => 'service', 'slug' => 'transfer-support', 'title' => '乗継サポート', 'excerpt' => '空港・駅の乗継は行程を伺って個別に確認します。', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">空港内、空港と駅、駅構内などの乗継支援は、必要時間、手続き、移動距離、施設規則を確認して個別にご案内します。</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">ご相談に必要な情報</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>到着便・列車と次の出発便・列車</li><li>ターミナル、駅、改札、乗換区間</li><li>旅客人数、手荷物、移動支援の必要性</li><li>入国、手荷物受取、再チェックインなど必要な手続き</li></ul><!-- /wp:list --><!-- wp:paragraph --><p>接続時間や施設側の規則により対応できない場合があります。オンライン送信だけでは予約確定になりません。</p><!-- /wp:paragraph -->', 'order' => 15),
    array('key' => 'attend', 'parent' => 'service', 'slug' => 'attend', 'title' => 'アテンドサービス', 'excerpt' => '標準の迎送範囲を超える同行は個別にご相談ください。', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">複数地点、一定時間の同行、会議・視察・観光との接続などは、行程と必要な役割を確認して個別にご案内します。</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">確認する内容</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>日時、開始・終了地点、予定時間</li><li>旅客人数、必要な言語</li><li>移動手段と車両の手配状況</li><li>現地で必要な支援と責任範囲</li></ul><!-- /wp:list -->', 'order' => 16),
    array('key' => 'area', 'slug' => 'area', 'title' => '対応エリア', 'excerpt' => '羽田・成田・東京駅・品川駅を中心に対応します。', 'content' => $areaIndex, 'order' => 20),
    array('key' => 'haneda', 'parent' => 'area', 'slug' => 'haneda-airport', 'title' => '羽田空港', 'excerpt' => '羽田空港第1・第2・第3ターミナルのお迎え・お見送り。', 'content' => jat_location_content('羽田空港の国内線・国際線で、到着ロビーのお迎え、車両へのご案内、出発時のチェックイン動線を支援します。', array('第1ターミナル', '第2ターミナル', '第3ターミナル'), array('到着口付近でサインボードを掲げて待機', '予約確定時に指定した到着ロビーまたは会合位置', '出発時は車寄せなど約束した位置で会合'), array('航空会社、便名、到着・出発ターミナルをお知らせください。', '国内線と国際線、当日の運用によりターミナルや到着口が変わる場合があります。', '配車場所、車寄せ、歩行動線は予約確定時に最新情報を確認します。')), 'order' => 21),
    array('key' => 'narita', 'parent' => 'area', 'slug' => 'narita-airport', 'title' => '成田空港', 'excerpt' => '成田空港第1・第2・第3ターミナルのお迎え・お見送り。', 'content' => jat_location_content('成田空港の各ターミナルで、到着ロビーのお迎え、車両へのご案内、出発時のチェックイン動線を支援します。', array('第1ターミナル', '第2ターミナル', '第3ターミナル'), array('到着ロビーの指定位置でサインボードを掲げて待機', '便と到着区域を確認して会合位置を指定', '出発時は車寄せなど約束した位置で会合'), array('航空会社、便名、到着・出発ターミナルをお知らせください。', '到着区域、ターミナル間移動、配車場所は当日の施設運用に従います。', '特殊な移動支援が必要な場合は申込時にお知らせください。')), 'order' => 22),
    array('key' => 'tokyo', 'parent' => 'area', 'slug' => 'tokyo-station', 'title' => '東京駅', 'excerpt' => '新幹線ホーム・主要改札・車寄せでのお迎え・お見送り。', 'content' => jat_location_content('東京駅の新幹線到着・出発に合わせ、号車・座席・改札情報を確認し、駅構内と車両の移動を支援します。', array('新幹線ホーム周辺', '主要改札', '八重洲側・丸の内側の指定車寄せ'), array('号車・座席付近または改札付近でサインボード待機', '入場制限や混雑時は改札外の指定位置で会合', '出発時は車寄せから改札・ホームへご案内'), array('列車名、列車番号、号車、座席をお知らせください。', '工事、混雑、入場券の取扱いにより会合方法が変わる場合があります。', '車両の配車側と駅側を事前に確認します。')), 'order' => 23),
    array('key' => 'shinagawa', 'parent' => 'area', 'slug' => 'shinagawa-station', 'title' => '品川駅', 'excerpt' => '新幹線ホーム・改札・車寄せでのお迎え・お見送り。', 'content' => jat_location_content('品川駅の新幹線到着・出発に合わせ、号車・座席・改札情報を確認し、駅構内と車両の移動を支援します。', array('新幹線ホーム周辺', '主要改札', '港南口側など予約時に指定する車寄せ'), array('号車・座席付近または改札付近でサインボード待機', '入場制限や混雑時は改札外の指定位置で会合', '出発時は車寄せから改札・ホームへご案内'), array('列車名、列車番号、号車、座席をお知らせください。', '駅施設の運用、雨天、混雑、工事により会合位置が変わる場合があります。', 'バリアフリー経路や手荷物が多い場合は申込時にお知らせください。')), 'order' => 24),
    array('key' => 'other-area', 'parent' => 'area', 'slug' => 'other-locations', 'title' => 'その他の空港・駅', 'excerpt' => 'その他の場所は行程を伺って対応可否を確認します。', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">羽田空港、成田空港、東京駅、品川駅以外の日本国内およびアジアの空港・駅も、日時、行程、必要な支援を伺い、ネットワークを確認して対応可否を個別にご案内します。すべての地域での対応を保証するものではありません。</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">確認に必要な情報</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>空港・駅の正式名称</li><li>便・列車と日時</li><li>旅客人数と当日の連絡先</li><li>希望する会合位置と終了地点</li><li>車両、手荷物、追加支援の有無</li></ul><!-- /wp:list -->', 'order' => 25),
    array('key' => 'flow', 'slug' => 'flow', 'title' => 'ご利用の流れ', 'excerpt' => 'お申し込みからサービス完了まで。', 'content' => $flow, 'order' => 30),
    array('key' => 'price', 'slug' => 'price', 'title' => '料金', 'excerpt' => '料金は内容を確認して個別にご案内します。', 'content' => $price, 'order' => 40),
    array('key' => 'cases', 'slug' => 'cases', 'title' => '導入事例', 'excerpt' => '公開許諾済みの事例を準備しています。', 'content' => $cases, 'status' => 'draft', 'order' => 50),
    array('key' => 'faq', 'slug' => 'faq', 'title' => 'よくあるご質問', 'excerpt' => '予約・当日・料金・法人利用などのご質問。', 'content' => $faq, 'order' => 60),
    array('key' => 'corporate', 'slug' => 'corporate', 'title' => '法人のお客様', 'excerpt' => '企業、旅行会社、車両会社、団体の迎送をご相談いただけます。', 'content' => $corporate, 'order' => 70),
    array('key' => 'recruit', 'slug' => 'recruit', 'title' => '採用情報', 'excerpt' => '日本全国の玄関口で、最高峰のホスピタリティを提供する「お迎えのスペシャリスト」を募集しています。', 'content' => $recruit, 'role' => 'recruit', 'status' => 'draft', 'order' => 75),
    array('key' => 'company', 'slug' => 'company', 'title' => '会社案内', 'excerpt' => 'Japan Airport Transfer の考え方と会社概要。', 'content' => $company, 'status' => 'draft', 'preserve_if_published' => true, 'order' => 80),
    array('key' => 'contact', 'parent' => 'company', 'slug' => 'contact', 'title' => 'お問い合わせ', 'excerpt' => 'サービス内容や法人利用をご相談ください。', 'content' => $contact, 'status' => 'draft', 'preserve_if_published' => true, 'order' => 81),
    array('key' => 'reservation', 'slug' => 'reservation', 'title' => 'オンライン申込', 'excerpt' => '五つの手順で必要事項をご入力ください。', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">サービス内容、旅客情報、サインボード、申請者・車両情報をご入力ください。送信時点では受付であり、予約確定ではありません。</p><!-- /wp:paragraph --><!-- wp:shortcode -->[jat_reservation_form]<!-- /wp:shortcode -->', 'order' => 90),
    array('key' => 'reservation-complete', 'parent' => 'reservation', 'slug' => 'complete', 'title' => 'お申し込みを受け付けました', 'excerpt' => '', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">お申し込みありがとうございます。受付番号と内容確認のご案内をメールでお送りします。</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>この時点では予約確定ではありません。担当者が内容、人員、場所、料金を確認し、あらためてご連絡します。</p><!-- /wp:paragraph -->', 'order' => 91),
    array('key' => 'privacy', 'slug' => 'privacy-policy', 'title' => 'プライバシーポリシー', 'excerpt' => '', 'content' => $legalDraft('プライバシーポリシー'), 'status' => 'draft', 'preserve_if_published' => true, 'order' => 100),
    array('key' => 'terms', 'slug' => 'terms', 'title' => 'サービス利用規約', 'excerpt' => '', 'content' => $legalDraft('サービス利用規約'), 'status' => 'draft', 'preserve_if_published' => true, 'order' => 101),
    array('key' => 'cancel', 'slug' => 'cancellation-policy', 'title' => 'キャンセルポリシー', 'excerpt' => '', 'content' => $legalDraft('キャンセルポリシー'), 'status' => 'draft', 'preserve_if_published' => true, 'order' => 102),
    array('key' => 'legal', 'slug' => 'legal', 'title' => '特定商取引法に基づく表示', 'excerpt' => '', 'content' => $legalDraft('特定商取引法に基づく表示'), 'status' => 'draft', 'preserve_if_published' => true, 'order' => 103),
    array('key' => 'accessibility', 'slug' => 'accessibility', 'title' => 'アクセシビリティ', 'excerpt' => '', 'content' => '<!-- wp:paragraph {"className":"jat-lead"} --><p class="jat-lead">年齢、障害、利用環境にかかわらず、できるだけ多くの方が情報と申込機能を利用できるサイトを目指します。</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">取り組み</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>キーボードでの操作と見える焦点表示</li><li>十分な文字サイズと色の対比</li><li>画像の代替文字と見出し構造</li><li>フォームの明確なラベル、エラー概要、入力内容の保持</li><li>動きを減らす設定への対応</li></ul><!-- /wp:list -->', 'order' => 104),
    array('key' => 'news', 'slug' => 'news', 'title' => 'お知らせ', 'excerpt' => '', 'content' => '', 'order' => 110),
);

$roleByKey = array(
    'service' => 'service',
    'airport-meet' => 'service',
    'airport-sending' => 'service',
    'station-meet' => 'service',
    'station-sending' => 'service',
    'transfer-support' => 'service',
    'attend' => 'service',
    'area' => 'location',
    'haneda' => 'location',
    'narita' => 'location',
    'tokyo' => 'location',
    'shinagawa' => 'location',
    'other-area' => 'location',
    'flow' => 'flow',
    'price' => 'price',
    'corporate' => 'corporate',
    'company' => 'company',
    'contact' => 'contact',
    'reservation' => 'reservation',
    'reservation-complete' => 'reservation',
    'privacy' => 'legal',
    'terms' => 'legal',
    'cancel' => 'legal',
    'legal' => 'legal',
);

$pageIds = array();
foreach ($pages as $page) {
    $parentId = isset($page['parent']) ? (int) ($pageIds[$page['parent']] ?? 0) : 0;
    $pageId = jat_seed_page($page, $parentId);
    $pageIds[$page['key']] = $pageId;

    if (! empty($page['preserve_if_published']) && get_post_status($pageId) === 'publish') {
        continue;
    }

    update_post_meta($pageId, 'jat_content_role', $page['role'] ?? ($roleByKey[$page['key']] ?? 'general'));
    update_post_meta($pageId, 'jat_sort_order', (int) ($page['order'] ?? 0));
}

$faqOrder = 0;
$expectedFaqSeedKeys = array();
foreach ($faqItems as $category => $items) {
    $term = term_exists($category, 'jat_topic');
    if (! $term) {
        $term = wp_insert_term($category, 'jat_topic');
    }
    $termId = ! is_wp_error($term) ? (int) (is_array($term) ? $term['term_id'] : $term) : 0;

    foreach ($items as $question => $answer) {
        $faqOrder += 10;
        $seedKey = hash('sha256', $category . "\n" . $question);
        $expectedFaqSeedKeys[] = $seedKey;
        $existing = get_posts(
            array(
                'post_type'      => 'jat_faq',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => 'jat_seed_key',
                'meta_value'     => $seedKey,
            )
        );
        $postData = array(
            'post_type'    => 'jat_faq',
            'post_status'  => 'publish',
            'post_title'   => $question,
            'post_content' => '<!-- wp:paragraph --><p>' . esc_html($answer) . '</p><!-- /wp:paragraph -->',
            'menu_order'   => $faqOrder,
        );
        if ($existing) {
            $postData['ID'] = (int) $existing[0];
            $faqId = wp_update_post(wp_slash($postData), true);
        } else {
            $faqId = wp_insert_post(wp_slash($postData), true);
        }
        if (is_wp_error($faqId)) {
            WP_CLI::warning(sprintf('FAQ「%s」を保存できませんでした: %s', $question, $faqId->get_error_message()));
            continue;
        }

        update_post_meta($faqId, 'jat_seed_key', $seedKey);
        update_post_meta($faqId, 'jat_sort_order', $faqOrder);
        if ($termId > 0) {
            wp_set_object_terms($faqId, array($termId), 'jat_topic', false);
        }
    }
}

$seededFaqIds = get_posts(
    array(
        'post_type'      => 'jat_faq',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => 'jat_seed_key',
                'compare' => 'EXISTS',
            ),
        ),
    )
);
foreach ($seededFaqIds as $seededFaqId) {
    $storedSeedKey = (string) get_post_meta($seededFaqId, 'jat_seed_key', true);
    if (! in_array($storedSeedKey, $expectedFaqSeedKeys, true)) {
        wp_delete_post((int) $seededFaqId, true);
    }
}

update_option('show_on_front', 'page');
update_option('page_on_front', $pageIds['home']);
update_option('page_for_posts', $pageIds['news']);
update_option('blogname', 'Japan Airport Transfer');
update_option('blogdescription', '空港・駅 ミート＆センディングサービス');
update_option('timezone_string', 'Asia/Tokyo');
update_option('date_format', 'Y年n月j日');
update_option('time_format', 'H:i');
update_option('permalink_structure', '/%postname%/');
update_option('blog_public', '0');
flush_rewrite_rules();

WP_CLI::success(sprintf('%d 件の固定ページを作成・更新しました。', count($pageIds)));
