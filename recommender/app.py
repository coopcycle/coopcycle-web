import datetime
import json
import os
import threading
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Literal

from fastapi import FastAPI, HTTPException, Query
from pydantic import BaseModel

MODEL_DIR = Path(os.environ.get("MODEL_DIR", "/models"))

_models: dict[str, dict] = {}       # {instance: {"product": model, "restaurant": model}}
_staging: dict[str, dict] = {}      # {instance: {"product": [...rows], "restaurant": [...rows]}}
_locks: dict[str, threading.Lock] = {}
_global_lock = threading.Lock()


def _instance_lock(instance: str) -> threading.Lock:
    with _global_lock:
        if instance not in _locks:
            _locks[instance] = threading.Lock()
        return _locks[instance]


def _instance_dir(instance: str) -> Path:
    return MODEL_DIR / instance


def _load_all_instances() -> None:
    from recommender import CollaborativeFilteringRecommender

    if not MODEL_DIR.exists():
        return

    for instance_dir in MODEL_DIR.iterdir():
        if not instance_dir.is_dir():
            continue
        instance = instance_dir.name
        entry: dict = {}
        for kind in ("product", "restaurant"):
            path = instance_dir / f"{kind}_recommender.joblib"
            if path.exists():
                entry[kind] = CollaborativeFilteringRecommender.load(str(path))
        if entry:
            with _instance_lock(instance):
                _models[instance] = entry
            print(f"Loaded models for instance '{instance}'")


@asynccontextmanager
async def lifespan(app: FastAPI):
    _load_all_instances()
    yield


app = FastAPI(title="CoopCycle Recommender", version="2.0.0", lifespan=lifespan)


class StartRequest(BaseModel):
    instance: str


class Interaction(BaseModel):
    customer_id: int
    item_id: int
    interaction_count: int


class PushRequest(BaseModel):
    instance: str
    type: Literal["product", "restaurant"]
    interactions: list[Interaction]


class CommitRequest(BaseModel):
    instance: str
    product_popular: list[int] = []
    restaurant_popular: list[int] = []
    product_restaurant_map: dict[str, int] = {}  # product_id (str) → restaurant_id


@app.get("/health")
def health():
    instances = {}
    if MODEL_DIR.exists():
        for instance_dir in MODEL_DIR.iterdir():
            if not instance_dir.is_dir():
                continue
            metadata_path = instance_dir / "metadata.json"
            if metadata_path.exists():
                with open(metadata_path) as f:
                    instances[instance_dir.name] = json.load(f)
    return {
        "status": "ok",
        "instances": instances,
        "loaded": list(_models.keys()),
    }


@app.post("/train/start")
def train_start(body: StartRequest):
    with _instance_lock(body.instance):
        _staging[body.instance] = {"product": [], "restaurant": []}
    return {"instance": body.instance}


@app.post("/train/push")
def train_push(body: PushRequest):
    with _instance_lock(body.instance):
        if body.instance not in _staging:
            raise HTTPException(status_code=400, detail=f"No active training session for '{body.instance}'. Call POST /train/start first.")
        _staging[body.instance][body.type].extend(r.model_dump() for r in body.interactions)
    return {"instance": body.instance, "type": body.type, "pushed": len(body.interactions)}


@app.post("/train/commit")
def train_commit(body: CommitRequest):
    from recommender import CollaborativeFilteringRecommender

    with _instance_lock(body.instance):
        if body.instance not in _staging:
            raise HTTPException(status_code=400, detail=f"No active training session for '{body.instance}'. Call POST /train/start first.")

        staged = _staging.pop(body.instance)

    product_interactions = staged["product"]
    restaurant_interactions = staged["restaurant"]

    # JSON object keys are always strings; convert to int for the model
    product_restaurant_map = {int(k): v for k, v in body.product_restaurant_map.items()}

    product_rec = CollaborativeFilteringRecommender()
    product_rec.fit(product_interactions, body.product_popular, item_key="item_id",
                    product_restaurant_map=product_restaurant_map)

    restaurant_rec = CollaborativeFilteringRecommender()
    restaurant_rec.fit(restaurant_interactions, body.restaurant_popular, item_key="item_id")

    instance_dir = _instance_dir(body.instance)
    instance_dir.mkdir(parents=True, exist_ok=True)

    product_rec.save(str(instance_dir / "product_recommender.joblib"))
    restaurant_rec.save(str(instance_dir / "restaurant_recommender.joblib"))

    metadata = {
        "trained_at": datetime.datetime.now(datetime.UTC).isoformat(),
        "product_interactions": len(product_interactions),
        "restaurant_interactions": len(restaurant_interactions),
    }
    with open(instance_dir / "metadata.json", "w") as f:
        json.dump(metadata, f)

    with _instance_lock(body.instance):
        _models[body.instance] = {"product": product_rec, "restaurant": restaurant_rec}

    return {
        "instance": body.instance,
        "product_interactions": len(product_interactions),
        "restaurant_interactions": len(restaurant_interactions),
        "trained_at": metadata["trained_at"],
    }


@app.get("/recommendations")
def recommendations(
    instance: str = Query(..., description="Instance identifier, e.g. coopcycle_paris"),
    customer: str = Query(..., description="Customer IRI e.g. /api/customers/1"),
    type: str = Query(..., description="'product' or 'restaurant'"),
    n: int = Query(5, ge=1, le=20),
    restaurant: str | None = Query(None, description="Restaurant IRI to scope product recommendations, e.g. /api/restaurants/1"),
):
    if type not in ("product", "restaurant"):
        raise HTTPException(status_code=400, detail="type must be 'product' or 'restaurant'")

    instance_models = _models.get(instance)
    if instance_models is None:
        raise HTTPException(status_code=503, detail=f"No trained model for instance '{instance}'. Run coopcycle:recommender:train.")

    model = instance_models.get(type)
    if model is None:
        raise HTTPException(status_code=503, detail=f"No {type} model for instance '{instance}'.")

    try:
        customer_id = int(customer.rstrip("/").split("/")[-1])
    except (ValueError, IndexError):
        raise HTTPException(status_code=400, detail="Invalid customer IRI — expected /api/customers/{id}")

    restaurant_id = int(restaurant.rstrip("/").split("/")[-1]) if restaurant else None

    item_ids = model.recommend(customer_id, n=n, restaurant_id=restaurant_id)

    prefix = "/api/products" if type == "product" else "/api/restaurants"
    return {"recommendations": [f"{prefix}/{item_id}" for item_id in item_ids]}
