import os
import psycopg2
import psycopg2.extras


def get_db_connection():
    return psycopg2.connect(
        host=os.environ.get("COOPCYCLE_DB_HOST", "postgres"),
        port=int(os.environ.get("COOPCYCLE_DB_PORT", 5432)),
        dbname=os.environ.get("COOPCYCLE_DB_NAME", "coopcycle"),
        user=os.environ.get("COOPCYCLE_DB_USER", "coopcycle"),
        password=os.environ.get("COOPCYCLE_DB_PASSWORD", ""),
    )


def get_product_interactions(conn) -> list[dict]:
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute("""
            SELECT o.customer_id, pv.product_id, COUNT(*) AS interaction_count
            FROM sylius_order_item oi
            JOIN sylius_order o ON o.id = oi.order_id
            JOIN sylius_product_variant pv ON pv.id = oi.variant_id
            JOIN sylius_product p ON p.id = pv.product_id
            WHERE o.state = 'fulfilled'
              AND o.customer_id IS NOT NULL
              AND p.deleted_at IS NULL
              AND p.enabled = TRUE
            GROUP BY o.customer_id, pv.product_id
        """)
        return [dict(row) for row in cur.fetchall()]


def get_restaurant_interactions(conn) -> list[dict]:
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute("""
            SELECT o.customer_id, ov.restaurant_id, COUNT(*) AS interaction_count
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = 'fulfilled'
              AND o.customer_id IS NOT NULL
            GROUP BY o.customer_id, ov.restaurant_id
        """)
        return [dict(row) for row in cur.fetchall()]


def get_popular_products(conn, limit: int = 20) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("""
            SELECT pv.product_id
            FROM sylius_order_item oi
            JOIN sylius_order o ON o.id = oi.order_id
            JOIN sylius_product_variant pv ON pv.id = oi.variant_id
            JOIN sylius_product p ON p.id = pv.product_id
            WHERE o.state = 'fulfilled'
              AND p.deleted_at IS NULL
              AND p.enabled = TRUE
            GROUP BY pv.product_id
            ORDER BY COUNT(*) DESC
            LIMIT %s
        """, (limit,))
        return [row[0] for row in cur.fetchall()]


def get_popular_restaurants(conn, limit: int = 10) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("""
            SELECT ov.restaurant_id
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = 'fulfilled'
            GROUP BY ov.restaurant_id
            ORDER BY COUNT(*) DESC
            LIMIT %s
        """, (limit,))
        return [row[0] for row in cur.fetchall()]
