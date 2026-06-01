#!/usr/bin/env python3
"""Train recommendation models. Usage: python train.py"""

import datetime
import json
import os

from db import (
    get_db_connection,
    get_popular_products,
    get_popular_restaurants,
    get_product_interactions,
    get_restaurant_interactions,
)
from recommender import CollaborativeFilteringRecommender

MODEL_DIR = os.environ.get("MODEL_DIR", "/models")


def train():
    print("Connecting to database...")
    conn = get_db_connection()
    try:
        print("Fetching product interactions...")
        product_interactions = get_product_interactions(conn)
        popular_products = get_popular_products(conn)

        print("Fetching restaurant interactions...")
        restaurant_interactions = get_restaurant_interactions(conn)
        popular_restaurants = get_popular_restaurants(conn)
    finally:
        conn.close()

    print(f"Training product recommender on {len(product_interactions)} interactions...")
    product_rec = CollaborativeFilteringRecommender()
    product_rec.fit(product_interactions, popular_products, item_key="product_id")
    product_rec.save(f"{MODEL_DIR}/product_recommender.joblib")
    print("  Product model saved.")

    print(f"Training restaurant recommender on {len(restaurant_interactions)} interactions...")
    restaurant_rec = CollaborativeFilteringRecommender()
    restaurant_rec.fit(restaurant_interactions, popular_restaurants, item_key="restaurant_id")
    restaurant_rec.save(f"{MODEL_DIR}/restaurant_recommender.joblib")
    print("  Restaurant model saved.")

    metadata = {
        "trained_at": datetime.datetime.utcnow().isoformat() + "Z",
        "product_interactions": len(product_interactions),
        "restaurant_interactions": len(restaurant_interactions),
    }
    with open(f"{MODEL_DIR}/metadata.json", "w") as f:
        json.dump(metadata, f)

    print("Training complete.")
    return metadata


if __name__ == "__main__":
    train()
