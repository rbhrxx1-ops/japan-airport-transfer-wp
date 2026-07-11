#!/usr/bin/env python3

import datetime as dt
import json
import secrets
import subprocess
import sys
import time
import uuid

import requests

BASE = "http://127.0.0.1:8080"
TOKEN_URL = f"{BASE}/wp-json/jat-reservation/v1/token"
ORDER_URL = f"{BASE}/wp-json/jat-reservation/v1/orders"
WP_PATH = "/home/ubuntu/japan-airport-transfer-wp/wordpress"

passed = 0
failed = 0
created_email = f"phase4-api-{secrets.token_hex(4)}@example.com"


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f"PASS: {message}")
    else:
        failed += 1
        print(f"FAIL: {message}")


def wp_eval(code: str) -> None:
    subprocess.run(
        ["wp", f"--path={WP_PATH}", "--allow-root", "eval", code],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )


def clear_rate_limits() -> None:
    wp_eval(
        "global $wpdb; "
        "$like = $wpdb->esc_like('_transient_jat_rate_') . '%'; "
        "$timeout_like = $wpdb->esc_like('_transient_timeout_jat_rate_') . '%'; "
        "$wpdb->query($wpdb->prepare(\"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s\", $like, $timeout_like));"
    )


def payload() -> dict:
    return {
        "website": "",
        "form_started_at": int(time.time()) - 5,
        "service_type": "airport_meet",
        "location_id": "haneda",
        "terminal_area": "第3ターミナル",
        "service_date": (dt.date.today() + dt.timedelta(days=14)).isoformat(),
        "scheduled_time": "15:30",
        "flight_number": "NH105",
        "train_number": "",
        "origin_destination": "東京",
        "quote_only": False,
        "lead_passenger_name": "山田 太郎",
        "passenger_count_adult": 2,
        "passenger_count_child": 0,
        "group_name": "",
        "luggage_count": 2,
        "checked_baggage": "yes",
        "emergency_phone": "+81 90-1234-5678",
        "mobility_support": [],
        "special_notes": "API統合試験",
        "signboard_text": "TARO YAMADA",
        "signboard_company": "",
        "service_language": "japanese",
        "customer_type": "individual",
        "company_name": "",
        "department": "",
        "applicant_name": "山田 太郎",
        "applicant_email": created_email,
        "applicant_email_confirmation": created_email,
        "applicant_phone": "+81 90-1234-5678",
        "contract_customer": "",
        "contract_code": "",
        "destination": "東京都内ホテル",
        "transport_type": "undecided",
        "driver_name": "",
        "driver_phone": "",
        "vehicle_info": "",
        "privacy_consent": True,
        "terms_consent": True,
        "marketing_consent": False,
        "final_confirm": True,
    }


def post(body: dict, nonce: str, key: str | None = None, origin: str = BASE) -> requests.Response:
    headers = {
        "Content-Type": "application/json",
        "Origin": origin,
        "X-WP-Nonce": nonce,
        "Idempotency-Key": key or str(uuid.uuid4()),
    }
    return requests.post(ORDER_URL, headers=headers, data=json.dumps(body), timeout=10)


try:
    clear_rate_limits()

    bad_origin = requests.get(TOKEN_URL, headers={"Origin": "https://attacker.example"}, timeout=10)
    bad_origin_json = bad_origin.json()
    check(bad_origin.status_code == 403 and bad_origin_json.get("code") == "jat_invalid_origin", "跨站 Origin 被拒绝")

    token_response = requests.get(TOKEN_URL, headers={"Origin": BASE}, timeout=10)
    token_json = token_response.json()
    nonce = token_json.get("nonce", "")
    check(token_response.status_code == 200 and bool(nonce), "同源请求可取得刷新 Nonce")
    check("入力内容を保持" in token_json.get("expires_message", ""), "Nonce 过期响应明确保留输入内容")

    expired = post(payload(), "invalid-nonce")
    expired_json = expired.json()
    check(
        expired.status_code == 403
        and expired_json.get("code") == "rest_cookie_invalid_nonce",
        "无效 Nonce 返回 WordPress 核心过期错误码",
    )
    js_source = open(
        "/home/ubuntu/japan-airport-transfer-wp/wp-content/plugins/jat-reservation/assets/js/reservation.js",
        encoding="utf-8",
    ).read()
    check(
        "'rest_cookie_invalid_nonce'" in js_source and "await refreshNonce()" in js_source,
        "前端识别核心过期错误码并自动刷新后重试",
    )

    spam_body = payload()
    spam_body["website"] = "bot"
    spam = post(spam_body, nonce)
    check(spam.status_code == 400 and spam.json().get("code") == "jat_spam_detected", "蜜罐命中后拒绝机器人提交")

    fast_body = payload()
    fast_body["form_started_at"] = int(time.time())
    fast = post(fast_body, nonce)
    check(fast.status_code == 400 and fast.json().get("code") == "jat_invalid_form_time", "时间陷阱拒绝异常快速提交")

    invalid_body = payload()
    invalid_body["applicant_email"] = "invalid"
    invalid_body["applicant_email_confirmation"] = "invalid"
    invalid = post(invalid_body, nonce)
    invalid_json = invalid.json()
    check(
        invalid.status_code == 422
        and invalid_json.get("code") == "jat_validation_failed"
        and "applicant_email" in invalid_json.get("data", {}).get("fields", {}),
        "服务器端字段错误以日语结构化返回",
    )

    clear_rate_limits()
    idem_key = str(uuid.uuid4())
    first = post(payload(), nonce, idem_key)
    first_json = first.json()
    second = post(payload(), nonce, idem_key)
    second_json = second.json()
    check(first.status_code == 201 and first_json.get("success") is True, "有效 REST 请求创建受付订单")
    check(
        second.status_code == 200
        and second_json.get("duplicate") is True
        and second_json.get("reference") == first_json.get("reference"),
        "相同幂等键重试返回原受付编号",
    )

    clear_rate_limits()
    rate_codes = []
    for _ in range(9):
        response = post(payload(), nonce, key="invalid-key")
        rate_codes.append((response.status_code, response.json().get("code")))
    check(
        all(code == (400, "jat_invalid_idempotency_key") for code in rate_codes[:8])
        and rate_codes[8] == (429, "jat_rate_limited"),
        "十分钟窗口内第九次提交被限流",
    )
finally:
    escaped_email = created_email.replace("'", "''")
    wp_eval(
        "global $wpdb; "
        f"$wpdb->delete(JAT_Reservation_DB::table_orders(), array('applicant_email' => '{escaped_email}'), array('%s'));"
    )
    clear_rate_limits()

print(f"RESULT: {passed} passed, {failed} failed")
sys.exit(0 if failed == 0 else 1)
