<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Form
{
    public static function init(): void
    {
        add_shortcode('jat_reservation_form', array(self::class, 'render'));
    }

    public static function render(): string
    {
        wp_enqueue_style(
            'jat-reservation',
            JAT_RESERVATION_URL . 'assets/css/reservation.css',
            array(),
            JAT_RESERVATION_VERSION
        );
        wp_enqueue_script(
            'jat-reservation',
            JAT_RESERVATION_URL . 'assets/js/reservation.js',
            array(),
            JAT_RESERVATION_VERSION,
            true
        );
        wp_add_inline_script(
            'jat-reservation',
            'window.JATReservationConfig = ' . wp_json_encode(
                array(
                    'endpoint' => esc_url_raw(rest_url('jat-reservation/v1/orders')),
                    'tokenEndpoint' => esc_url_raw(rest_url('jat-reservation/v1/token')),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'storageKey' => 'jat_reservation_draft_v1',
                    'completeUrl' => esc_url_raw(home_url('/reservation/complete/')),
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . ';',
            'before'
        );

        ob_start();
        ?>
        <section class="jat-reservation" aria-labelledby="jat-reservation-title">
            <div class="jat-reservation__intro">
                <p class="jat-reservation__eyebrow">ONLINE APPLICATION</p>
                <h2 id="jat-reservation-title">オンライン申込</h2>
                <p>五つの手順でお申し込み内容をお知らせください。送信時点では受付であり、予約確定ではありません。</p>
                <p class="jat-reservation__notice">料金、対応可否、最終的な会合場所は、担当者が内容を確認した後にご案内します。</p>
            </div>

            <nav class="jat-reservation__progress" aria-label="お申し込みの進行状況">
                <ol>
                    <?php foreach (array('サービス・日時', 'ご利用者情報', '迎送内容', '申込者・車両', '入力内容の確認') as $index => $label) : ?>
                        <li data-progress-step="<?php echo esc_attr((string) ($index + 1)); ?>">
                            <span><?php echo esc_html((string) ($index + 1)); ?></span><?php echo esc_html($label); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="jat-reservation__error-summary" tabindex="-1" role="alert" aria-live="assertive" hidden>
                <h3>入力内容をご確認ください</h3>
                <ul></ul>
            </div>

            <form id="jat-reservation-form" class="jat-reservation__form" novalidate>
                <input type="hidden" name="form_started_at" value="<?php echo esc_attr((string) time()); ?>">
                <div class="jat-reservation__honeypot" aria-hidden="true">
                    <label for="jat-website">ウェブサイト</label>
                    <input id="jat-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <fieldset data-step="1">
                    <legend><span>手順1</span> サービス・ご利用日時</legend>
                    <?php self::required_note(); ?>
                    <div class="jat-form-grid jat-form-grid--2">
                        <?php self::select('service_type', 'ご希望のサービス', array(
                            'airport_meet' => '空港お迎え',
                            'airport_send' => '空港お見送り',
                            'station_meet' => '駅お迎え',
                            'station_send' => '駅お見送り',
                            'transfer_support' => '乗継サポート（要相談）',
                            'attend' => 'アテンドサービス（要相談）',
                        ), true); ?>
                        <?php self::select('location_id', '空港・駅', array(
                            'haneda' => '羽田空港',
                            'narita' => '成田空港',
                            'tokyo' => '東京駅',
                            'shinagawa' => '品川駅',
                            'other' => 'その他（要相談）',
                        ), true); ?>
                        <?php self::input('terminal_area', 'ターミナル・改札・会合区域', 'text', false, '例：第3ターミナル、八重洲北口'); ?>
                        <?php self::input('service_date', 'ご利用日', 'date', true); ?>
                        <?php self::input('scheduled_time', '到着・出発予定時刻', 'time', true); ?>
                        <div data-service-group="airport">
                            <?php self::input('flight_number', '便名・便番号', 'text', true, '例：JL012'); ?>
                        </div>
                        <div data-service-group="station">
                            <?php self::input('train_number', '列車名・列車番号', 'text', true, '例：のぞみ24号'); ?>
                        </div>
                        <?php self::input('origin_destination', '出発地・到着地', 'text', true, '例：東京都内ホテル、京都駅'); ?>
                    </div>
                    <label class="jat-checkbox"><input type="checkbox" name="quote_only" value="1"> まず概算・対応可否を相談したい</label>
                </fieldset>

                <fieldset data-step="2" hidden>
                    <legend><span>手順2</span> ご利用者情報</legend>
                    <?php self::required_note(); ?>
                    <div class="jat-form-grid jat-form-grid--2">
                        <?php self::input('lead_passenger_name', '代表者名', 'text', true, '例：TARO YAMADA'); ?>
                        <?php self::input('group_name', '団体名', 'text', false); ?>
                        <?php self::input('passenger_count_adult', '大人人数', 'number', true, '', '1', '99'); ?>
                        <?php self::input('passenger_count_child', '子ども人数', 'number', false, '', '0', '99'); ?>
                        <?php self::input('luggage_count', '手荷物の個数', 'number', true, '', '0', '99'); ?>
                        <div data-service-group="airport">
                            <?php self::select('checked_baggage', '受託手荷物', array('yes' => 'あり', 'no' => 'なし', 'undecided' => '未定'), true); ?>
                        </div>
                        <?php self::input('emergency_phone', '当日の緊急連絡先', 'tel', true, '例：+81 90 1234 5678'); ?>
                    </div>
                    <fieldset class="jat-field-group">
                        <legend>移動・行動に関する配慮</legend>
                        <div class="jat-check-grid">
                            <?php self::checkbox('mobility_support[]', 'wheelchair', '車いすを利用'); ?>
                            <?php self::checkbox('mobility_support[]', 'walking', '長距離歩行に配慮が必要'); ?>
                            <?php self::checkbox('mobility_support[]', 'infant', '乳幼児を同伴'); ?>
                            <?php self::checkbox('mobility_support[]', 'support_other', 'その他の配慮を相談'); ?>
                        </div>
                    </fieldset>
                </fieldset>

                <fieldset data-step="3" hidden>
                    <legend><span>手順3</span> お出迎え・お見送り内容</legend>
                    <?php self::required_note(); ?>
                    <div class="jat-form-grid jat-form-grid--2">
                        <div data-service-group="meet">
                            <?php self::input('signboard_text', 'サインボード表示名', 'text', true, '例：TARO YAMADA', '', '', '80'); ?>
                        </div>
                        <div data-service-group="meet">
                            <?php self::input('signboard_company', '会社名・団体名の表示', 'text', false, '', '', '', '120'); ?>
                        </div>
                        <?php self::select('service_language', 'ご希望の対応言語', array(
                            'japanese' => '日本語',
                            'consultation' => 'その他の言語（要相談）',
                        ), true); ?>
                    </div>
                    <div class="jat-sign-preview" data-service-group="meet" aria-live="polite">
                        <p>サインボード表示（プレビューはイメージです）</p>
                        <strong data-sign-preview>お客様名</strong>
                        <span data-sign-company-preview></span>
                    </div>
                    <?php self::textarea('special_notes', 'ご希望・配慮事項', false, '会合方法、移動支援、言語、手荷物など、確認してほしい事項をご記入ください。'); ?>
                    <p class="jat-form-help" data-consultation-note hidden>乗継・アテンドはご相談として受け付けます。対応可否、内容、料金は担当者が確認後にご案内します。</p>
                    <p class="jat-form-help">追加の現地支援は内容を確認して個別にご案内します。未確認のサービスを自動確定することはありません。</p>
                </fieldset>

                <fieldset data-step="4" hidden>
                    <legend><span>手順4</span> お申込者・車両情報</legend>
                    <?php self::required_note(); ?>
                    <fieldset class="jat-field-group">
                        <legend>お申込者区分 <span class="jat-required">必須</span></legend>
                        <div class="jat-radio-grid">
                            <?php self::radio('customer_type', 'individual', '個人'); ?>
                            <?php self::radio('customer_type', 'corporate', '法人・団体'); ?>
                        </div>
                    </fieldset>
                    <div class="jat-form-grid jat-form-grid--2">
                        <div data-customer-group="corporate">
                            <?php self::input('company_name', '会社名・団体名', 'text', true); ?>
                        </div>
                        <div data-customer-group="corporate">
                            <?php self::input('department', '部署名', 'text', false); ?>
                        </div>
                        <?php self::input('applicant_name', 'ご担当者名', 'text', true); ?>
                        <?php self::input('applicant_email', 'メールアドレス', 'email', true); ?>
                        <?php self::input('applicant_email_confirmation', 'メールアドレス（確認）', 'email', true); ?>
                        <?php self::input('applicant_phone', '電話番号', 'tel', true); ?>
                        <div data-customer-group="corporate">
                            <?php self::select('contract_customer', '既存の法人契約', array('yes' => 'あり', 'no' => 'なし', 'unknown' => '不明'), false); ?>
                        </div>
                        <div data-contract-group="yes">
                            <?php self::input('contract_code', '契約コード', 'text', false); ?>
                        </div>
                        <?php self::input('destination', 'ご案内先・目的地', 'text', true); ?>
                        <div data-service-group="meet">
                            <?php self::select('transport_type', '会合後の交通手段', array(
                                'arranged_vehicle' => '当社または依頼元が手配した車両',
                                'customer_vehicle' => 'お客様側の手配車両',
                                'public_transport' => '公共交通機関',
                                'undecided' => '未定・要相談',
                            ), true); ?>
                        </div>
                    </div>
                    <div class="jat-form-grid jat-form-grid--2" data-vehicle-group="customer_vehicle">
                        <?php self::input('driver_name', '乗務員名', 'text', false); ?>
                        <?php self::input('driver_phone', '乗務員連絡先', 'tel', false); ?>
                        <?php self::input('vehicle_info', '車種・車番・配車場所', 'text', false); ?>
                    </div>
                </fieldset>

                <fieldset data-step="5" hidden>
                    <legend><span>手順5</span> 入力内容の確認</legend>
                    <p>以下の内容をご確認ください。修正する場合は各項目の「修正する」または「戻る」を選択してください。</p>
                    <div class="jat-reservation__summary" data-summary></div>
                    <div class="jat-consents">
                        <label class="jat-checkbox"><input type="checkbox" name="privacy_consent" value="1" required> <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener">プライバシーポリシー</a>に同意します <span class="jat-required">必須</span></label>
                        <label class="jat-checkbox"><input type="checkbox" name="terms_consent" value="1" required> <a href="<?php echo esc_url(home_url('/terms/')); ?>" target="_blank" rel="noopener">利用規約</a>と<a href="<?php echo esc_url(home_url('/cancellation-policy/')); ?>" target="_blank" rel="noopener">キャンセルポリシー</a>に同意します <span class="jat-required">必須</span></label>
                        <label class="jat-checkbox"><input type="checkbox" name="marketing_consent" value="1"> サービスに関するお知らせを受け取ります（任意）</label>
                        <label class="jat-checkbox jat-checkbox--final"><input type="checkbox" name="final_confirm" value="1" required> 送信は予約確定ではなく、担当者による確認後に条件が確定することを確認しました <span class="jat-required">必須</span></label>
                    </div>
                </fieldset>

                <div class="jat-reservation__actions">
                    <button type="button" class="jat-button jat-button--secondary" data-action="previous" hidden>戻る</button>
                    <button type="button" class="jat-button" data-action="next">次へ</button>
                    <button type="submit" class="jat-button" data-action="submit" hidden>この内容で申し込む</button>
                </div>
                <p class="jat-reservation__status" role="status" aria-live="polite"></p>
            </form>

            <section class="jat-reservation__success" tabindex="-1" hidden aria-live="polite">
                <p class="jat-reservation__eyebrow">APPLICATION RECEIVED</p>
                <h2>お申し込みを受け付けました</h2>
                <p data-success-message></p>
                <dl><dt>受付番号</dt><dd data-reference></dd><dt>現在の状態</dt><dd>受付済み</dd></dl>
                <p>この時点では予約確定ではありません。担当者が内容を確認し、あらためてご連絡します。</p>
                <button type="button" class="jat-button jat-button--secondary" data-action="new-application">別のお申し込みを入力する</button>
            </section>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function required_note(): void
    {
        echo '<p class="jat-form-help"><span class="jat-required">必須</span> の項目をご入力ください。</p>';
    }

    private static function input(
        string $name,
        string $label,
        string $type,
        bool $required,
        string $placeholder = '',
        string $min = '',
        string $max = '',
        string $maxlength = '190'
    ): void {
        printf(
            '<label class="jat-field" for="jat-%1$s"><span>%2$s%3$s</span><input id="jat-%1$s" name="%1$s" type="%4$s"%5$s%6$s%7$s%8$s%9$s aria-describedby="jat-%1$s-error"><small id="jat-%1$s-error" class="jat-field-error"></small></label>',
            esc_attr($name),
            esc_html($label),
            $required ? ' <span class="jat-required">必須</span>' : ' <span class="jat-optional">任意</span>',
            esc_attr($type),
            $required ? ' required' : '',
            $placeholder !== '' ? ' placeholder="' . esc_attr($placeholder) . '"' : '',
            $min !== '' ? ' min="' . esc_attr($min) . '"' : '',
            $max !== '' ? ' max="' . esc_attr($max) . '"' : '',
            ! in_array($type, array('number', 'date', 'time'), true) ? ' maxlength="' . esc_attr($maxlength) . '"' : ''
        );
    }

    private static function select(string $name, string $label, array $options, bool $required): void
    {
        printf(
            '<label class="jat-field" for="jat-%1$s"><span>%2$s%3$s</span><select id="jat-%1$s" name="%1$s"%4$s aria-describedby="jat-%1$s-error"><option value="">選択してください</option>',
            esc_attr($name),
            esc_html($label),
            $required ? ' <span class="jat-required">必須</span>' : ' <span class="jat-optional">任意</span>',
            $required ? ' required' : ''
        );
        foreach ($options as $value => $option_label) {
            printf('<option value="%s">%s</option>', esc_attr((string) $value), esc_html((string) $option_label));
        }
        printf('</select><small id="jat-%1$s-error" class="jat-field-error"></small></label>', esc_attr($name));
    }

    private static function textarea(string $name, string $label, bool $required, string $placeholder): void
    {
        printf(
            '<label class="jat-field jat-field--wide" for="jat-%1$s"><span>%2$s%3$s</span><textarea id="jat-%1$s" name="%1$s" rows="5" maxlength="1000"%4$s placeholder="%5$s" aria-describedby="jat-%1$s-error"></textarea><small id="jat-%1$s-error" class="jat-field-error"></small></label>',
            esc_attr($name),
            esc_html($label),
            $required ? ' <span class="jat-required">必須</span>' : ' <span class="jat-optional">任意</span>',
            $required ? ' required' : '',
            esc_attr($placeholder)
        );
    }

    private static function checkbox(string $name, string $value, string $label): void
    {
        printf('<label class="jat-checkbox"><input type="checkbox" name="%s" value="%s"> %s</label>', esc_attr($name), esc_attr($value), esc_html($label));
    }

    private static function radio(string $name, string $value, string $label): void
    {
        printf('<label class="jat-radio"><input type="radio" name="%s" value="%s" required> %s</label>', esc_attr($name), esc_attr($value), esc_html($label));
    }
}
