from collections import defaultdict

import numpy as np
import joblib
from scipy.sparse import csr_matrix
from sklearn.neighbors import NearestNeighbors


class CollaborativeFilteringRecommender:
    def __init__(self, n_neighbors: int = 10):
        self.n_neighbors = n_neighbors
        self.model = NearestNeighbors(metric="cosine", algorithm="brute", n_neighbors=n_neighbors)
        self.user_item_matrix: csr_matrix | None = None
        self.user_index: dict[int, int] = {}
        self.item_index: dict[int, int] = {}
        self.item_reverse: dict[int, int] = {}
        self.popular_items: list[int] = []

    def fit(self, interactions: list[dict], popular_items: list[int], item_key: str = "product_id", product_restaurant_map: dict[int, int] | None = None) -> None:
        self.popular_items = popular_items
        self.product_restaurant_map: dict[int, int] = product_restaurant_map or {}

        if not interactions:
            return

        users = list({row["customer_id"] for row in interactions})
        items = list({row[item_key] for row in interactions})

        self.user_index = {u: i for i, u in enumerate(users)}
        self.item_index = {item: i for i, item in enumerate(items)}
        self.item_reverse = {i: item for item, i in self.item_index.items()}

        rows = [self.user_index[row["customer_id"]] for row in interactions]
        cols = [self.item_index[row[item_key]] for row in interactions]
        # log(1 + count) weighting dampens high-frequency bias
        data = np.log1p([row["interaction_count"] for row in interactions]).tolist()

        self.user_item_matrix = csr_matrix(
            (data, (rows, cols)),
            shape=(len(users), len(items)),
        )

        n = min(self.n_neighbors, len(users))
        self.model.set_params(n_neighbors=n)
        self.model.fit(self.user_item_matrix)

    def _filter_by_restaurant(self, items: list[int], restaurant_id: int | None) -> list[int]:
        if not restaurant_id or not self.product_restaurant_map:
            return items
        return [pid for pid in items if self.product_restaurant_map.get(pid) == restaurant_id]

    def recommend(self, customer_id: int, n: int = 5, restaurant_id: int | None = None) -> list[int]:
        if self.user_item_matrix is None or customer_id not in self.user_index:
            return self._filter_by_restaurant(self.popular_items, restaurant_id)[:n]

        user_idx = self.user_index[customer_id]
        user_vector = self.user_item_matrix[user_idx]

        k = min(self.model.n_neighbors + 1, self.user_item_matrix.shape[0])
        distances, indices = self.model.kneighbors(user_vector, n_neighbors=k)

        similar_users = indices.flatten()[1:]  # exclude the user themselves
        similarities = 1.0 - distances.flatten()[1:]

        already_ordered = set(self.user_item_matrix[user_idx].indices)

        item_scores: dict[int, float] = {}
        for similar_user_idx, similarity in zip(similar_users, similarities):
            for item_col in self.user_item_matrix[similar_user_idx].indices:
                if item_col not in already_ordered:
                    item_scores[item_col] = item_scores.get(item_col, 0.0) + similarity

        sorted_items = sorted(item_scores.items(), key=lambda x: x[1], reverse=True)
        candidates = [self.item_reverse[col] for col, _ in sorted_items]
        recommendations = self._filter_by_restaurant(candidates, restaurant_id)[:n]

        # pad with popular items if not enough recommendations
        if len(recommendations) < n:
            seen = set(recommendations) | {self.item_reverse.get(c) for c in already_ordered}
            for item_id in self._filter_by_restaurant(self.popular_items, restaurant_id):
                if item_id not in seen:
                    recommendations.append(item_id)
                    if len(recommendations) >= n:
                        break

        return recommendations

    def save(self, path: str) -> None:
        joblib.dump(self, path)

    @classmethod
    def load(cls, path: str) -> "CollaborativeFilteringRecommender":
        return joblib.load(path)


class FrequentlyBoughtTogetherRecommender:
    def __init__(self):
        self.cooccurrence: dict[int, dict[int, int]] = {}
        self.product_counts: dict[int, int] = {}
        self.total_orders: int = 0
        self.product_restaurant_map: dict[int, int] = {}
        self.popular_by_restaurant: dict[int, list[int]] = {}

    def fit(self, order_items: list[dict], product_restaurant_map: dict[int, int]) -> None:
        self.product_restaurant_map = product_restaurant_map

        orders: dict[int, set[int]] = defaultdict(set)
        for item in order_items:
            orders[item["order_id"]].add(item["item_id"])

        self.total_orders = len(orders)
        cooccurrence: dict = defaultdict(lambda: defaultdict(int))
        product_counts: dict = defaultdict(int)

        for items in orders.values():
            items_list = list(items)
            for pid in items_list:
                product_counts[pid] += 1
            for i, a in enumerate(items_list):
                for b in items_list[i + 1:]:
                    cooccurrence[a][b] += 1
                    cooccurrence[b][a] += 1

        self.cooccurrence = {k: dict(v) for k, v in cooccurrence.items()}
        self.product_counts = dict(product_counts)

        by_restaurant: dict = defaultdict(list)
        for pid, _ in sorted(product_counts.items(), key=lambda x: -x[1]):
            rest = product_restaurant_map.get(pid)
            if rest:
                by_restaurant[rest].append(pid)
        self.popular_by_restaurant = dict(by_restaurant)

    def recommend(self, product_id: int, restaurant_id: int | None = None, n: int = 5) -> list[int]:
        candidates = dict(self.cooccurrence.get(product_id, {}))

        if restaurant_id:
            candidates = {
                pid: c for pid, c in candidates.items()
                if self.product_restaurant_map.get(pid) == restaurant_id
            }

        if not candidates:
            fallback = self.popular_by_restaurant.get(restaurant_id, []) if restaurant_id else []
            return [pid for pid in fallback if pid != product_id][:n]

        count_a = self.product_counts.get(product_id, 1)
        scored = []
        for pid, cooc_count in candidates.items():
            count_b = self.product_counts.get(pid, 1)
            # lift: how much more likely A and B are bought together vs. independently
            lift = (cooc_count * self.total_orders) / (count_a * count_b)
            scored.append((pid, lift))

        scored.sort(key=lambda x: -x[1])
        result = [pid for pid, _ in scored[:n]]

        if len(result) < n and restaurant_id:
            seen = set(result) | {product_id}
            for pid in self.popular_by_restaurant.get(restaurant_id, []):
                if pid not in seen:
                    result.append(pid)
                    if len(result) >= n:
                        break

        return result

    def save(self, path: str) -> None:
        joblib.dump(self, path)

    @classmethod
    def load(cls, path: str) -> "FrequentlyBoughtTogetherRecommender":
        return joblib.load(path)
