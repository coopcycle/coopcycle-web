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

  async request({ method, url, data, params, headers }, depth = 0) {
    const sendHeaders = {
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json',
      Authorization: `Bearer ${this.jwt}`,
      ...headers,
    }

    try {
      const response = await axios({
        method,
        url,
        data,
        params,
        headers: sendHeaders,
      });
      return { response: response.data, error: null };
    } catch (err) {
      if (err.response) {
        if (err.response.status === 401) {
          if (depth < 3) {
            await this._refreshToken();
            return await this.request(
              {
                method,
                url,
                data,
                params,
                headers: sendHeaders
              },
              depth + 1,
            );
          }
        }
      }
      return { response: null, error: err };
    }
  }

  async get(url, params = {}, headers = {}) {
    return await this.request({ method: "GET", url, params, headers });
  }

  async head(url, headers = {}) {
    return await this.request({ method: "HEAD", url, headers });
  }

  async post(url, data, headers = {}) {
    return await this.request({ method: "POST", url, data, headers });
  }

  async put(url, data, headers = {}) {
    return await this.request({ method: "PUT", url, data, headers });
  }

  async patch(url, data, headers = {}) {
    return await this.request({ method: "PATCH", url, data, headers });
  }

  async delete(url, headers = {}) {
    return await this.request({ method: "DELETE", url, headers });
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
