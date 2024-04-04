import axios from "axios";

const user = JSON.parse(
  document.head.querySelector('meta[name="application-auth-user"]').content,
);
const jwt = document.head.querySelector(
  'meta[name="application-auth-jwt"]',
).content;

class httpClient {
  constructor() {
    this.jwt = jwt;
  }

  setToken(jwt) {
    this.jwt = jwt;
  }

  async request({ method, url, data, headers = {} }, depth = 0) {
    try {
      const response = await axios({
        method,
        url,
        data,
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          Authorization: `Bearer ${this.jwt}`,
          ...headers,
        },
      });
      return { response: response.data, error: null };
    } catch (err) {
      if (err.response) {
        if (err.response.status === 401) {
          if (depth < 3) {
            await this._refreshToken();
            return await this.request(
              { method, url, data, headers },
              depth + 1,
            );
          }
        }
      }
      return { response: null, error: err };
    }
  }

  async get(url) {
    return await this.request({ method: "GET", url });
  }

  async post(url, data) {
    return await this.request({ method: "POST", url, data });
  }

  async put(url, data) {
    return await this.request({ method: "PUT", url, data });
  }

  async patch(url, data) {
    return await this.request({ method: "PATCH", url, data });
  }

  async _refreshToken() {
    const token = await fetch(window.Routing.generate("profile_jwt"), {
      method: "get",
    });
    const { jwt } = await token.json();
    this.setToken(jwt);
  }
}

window["_auth"] = {
  isAuth: user !== null,
  jwt: jwt !== "" ? jwt : null,
  user,
  httpClient,
};
