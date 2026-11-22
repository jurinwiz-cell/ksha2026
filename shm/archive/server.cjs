var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __copyProps = (to, from, except, desc) => {
  if (from && typeof from === "object" || typeof from === "function") {
    for (let key of __getOwnPropNames(from))
      if (!__hasOwnProp.call(to, key) && key !== except)
        __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
  }
  return to;
};
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
  // If the importer is in node compatibility mode or this is not an ESM
  // file that has been converted to a CommonJS file using a Babel-
  // compatible transform (i.e. "__esModule" has not been set), then set
  // "default" to the CommonJS "module.exports" for node compatibility.
  isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
  mod
));

// server.ts
var import_express = __toESM(require("express"), 1);
var import_path = __toESM(require("path"), 1);
var import_fs = __toESM(require("fs"), 1);
var import_vite = require("vite");
var app = (0, import_express.default)();
var PORT = 3e3;
var DB_FILE = import_path.default.join(process.cwd(), "db-data.json");
app.use(import_express.default.json());
function getDB() {
  if (!import_fs.default.existsSync(DB_FILE)) {
    const initialDB = {
      users: [
        {
          id: 1,
          email: "green@ksha.us",
          password_hash: "admin123",
          // fallback simple matching for simulation
          name: "\uAD00\uB9AC\uC790\uB2D8 (\uB098)",
          role: "admin",
          created_at: "2026-05-30 08:19:15"
        },
        {
          id: 2,
          email: "chlim@assembly.go.kr",
          password_hash: "user123",
          name: "\uAD6D\uD68C \uBCF4\uC88C\uAD00 \uC784\uCCA0\uC218",
          role: "user",
          created_at: "2026-05-28 14:20:00"
        },
        {
          id: 3,
          email: "jschoi@nars.re.kr",
          password_hash: "user123",
          name: "\uC815\uCC45\uC2E4 \uC5F0\uAD6C\uC6D0 \uCD5C\uC9C0\uC218",
          role: "user",
          created_at: "2026-05-24 09:12:00"
        }
      ],
      workspaces: [
        { id: "ws_ai_tf", name: "AI \uBC0F \uC2E0\uAE30\uC220 \uD2B9\uBCC4 \uACF5\uB3D9 \uC785\uBC95 TF", created_by: 1, created_at: "2026-05-10 14:00" },
        { id: "ws_climate", name: "\uAE30\uD6C4\uC704\uAE30 \uADF9\uBCF5 \uD0C4\uC18C\uC911\uB9BD \uD2B9\uBCC4\uD3EC\uB7FC", created_by: 2, created_at: "2026-05-15 11:30" },
        { id: "ws_local", name: "\uC9C0\uBC29\uC18C\uBA78 \uC2E4\uC9C8\uB300\uC751 \uAD6D\uD68C \uAE30\uD68D\uD611\uC758\uCCB4", created_by: 3, created_at: "2026-05-20 09:15" }
      ],
      workspace_members: [
        { id: 1, workspace_id: "ws_ai_tf", user_id: 1, role: "owner" },
        { id: 2, workspace_id: "ws_climate", user_id: 2, role: "owner" },
        { id: 3, workspace_id: "ws_local", user_id: 3, role: "owner" }
      ],
      items: [
        {
          id: 101,
          source: "assembly",
          source_key: "asm_2026_001",
          mtr_ttl: "\uC778\uACF5\uC9C0\uB2A5(AI) \uC2DC\uB300\uC758 \uAC1C\uC778\uC815\uBCF4 \uBCF4\uD638 \uBC0F \uBC95\uC81C\uB3C4\uC801 \uB300\uC751 \uBC29\uC548",
          mtr_div: "\uC785\uBC95\uC870\uC0AC\uCC98 \uC5F0\uAD6C\uBCF4\uACE0\uC11C",
          wrt_dt: "2026-03-15",
          link_url: "https://www.nars.go.kr/report/view.do?reportId=001"
        },
        {
          id: 102,
          source: "assembly",
          source_key: "asm_2026_002",
          mtr_ttl: "\uAE30\uD6C4\uC704\uAE30 \uADF9\uBCF5\uC744 \uC704\uD55C \uAD6D\uAC00 \uC5D0\uB108\uC9C0 \uBBF9\uC2A4 \uC7AC\uC870\uC815 \uBC0F \uC785\uBC95 \uACFC\uC81C",
          mtr_div: "\uAD6D\uD68C\uBBF8\uB798\uC5F0\uAD6C\uC6D0 \uAE30\uD68D\uC870\uC0AC",
          wrt_dt: "2026-04-10",
          link_url: "https://www.nars.go.kr/report/view.do?reportId=002"
        },
        {
          id: 103,
          source: "assembly",
          source_key: "asm_2026_003",
          mtr_ttl: "\uC9C0\uBC29\uC18C\uBA78 \uC704\uC9C0\uC5ED \uC9C0\uC6D0 \uD2B9\uBCC4\uBC95 \uAC1C\uC815 \uBC0F \uC138\uC81C \uD61C\uD0DD \uD6A8\uACFC \uBD84\uC11D",
          mtr_div: "\uC608\uC0B0\uC815\uCC45\uCC98 \uC815\uCC45\uBD84\uC11D",
          wrt_dt: "2026-05-12",
          link_url: "https://www.nars.go.kr/report/view.do?reportId=003"
        }
      ],
      library_entries: [
        {
          id: 1,
          workspace_id: "ws_ai_tf",
          item_id: 101,
          created_by: 1,
          note: "\uCD08\uC548 \uBC95\uC801 \uADFC\uAC70 \uC218\uB9BD\uC6A9\uC73C\uB85C \uC2A4\uD06C\uB7A9.",
          is_favorite: true,
          created_at: "2026-05-30 08:20:00"
        },
        {
          id: 2,
          workspace_id: "ws_climate",
          item_id: 102,
          created_by: 2,
          note: "\uAE30\uD6C4\uC704\uAE30 \uC5D0\uB108\uC9C0 \uD2B9\uBCC4\uC138\uC158 \uBC1C\uC81C \uC608\uC815 \uC790\uB8CC \uC911 \uC6B0\uC120 \uBC1C\uAD74\uBB3C.",
          is_favorite: false,
          created_at: "2026-05-30 08:21:00"
        }
      ]
    };
    import_fs.default.writeFileSync(DB_FILE, JSON.stringify(initialDB, null, 2));
  }
  return JSON.parse(import_fs.default.readFileSync(DB_FILE, "utf-8"));
}
function saveDB(data) {
  import_fs.default.writeFileSync(DB_FILE, JSON.stringify(data, null, 2));
}
var activeSessions = {};
function getMe(req) {
  const authHeader = req.headers["authorization"] || req.headers["x-session-id"];
  if (!authHeader) return null;
  const token = String(authHeader).replace("Bearer ", "");
  return activeSessions[token] || null;
}
var MOCK_MATERIALS_CACHE = [
  {
    source_key: "asm_2026_001",
    mtr_ttl: "\uC778\uACF5\uC9C0\uB2A5(AI) \uC2DC\uB300\uC758 \uAC1C\uC778\uC815\uBCF4 \uBCF4\uD638 \uBC0F \uBC95\uC81C\uB3C4\uC801 \uB300\uC751 \uBC29\uC548",
    mtr_div: "\uC785\uBC95\uC870\uC0AC\uCC98 \uC5F0\uAD6C\uBCF4\uACE0\uC11C",
    wrt_dt: "2026-03-15",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=001"
  },
  {
    source_key: "asm_2026_002",
    mtr_ttl: "\uAE30\uD6C4\uC704\uAE30 \uADF9\uBCF5\uC744 \uC704\uD55C \uAD6D\uAC00 \uC5D0\uB108\uC9C0 \uBBF9\uC2A4 \uC7AC\uC870\uC815 \uBC0F \uC785\uBC95 \uACFC\uC81C",
    mtr_div: "\uAD6D\uD68C\uBBF8\uB798\uC5F0\uAD6C\uC6D0 \uAE30\uD68D\uC870\uC0AC",
    wrt_dt: "2026-04-10",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=002"
  },
  {
    source_key: "asm_2026_003",
    mtr_ttl: "\uC9C0\uBC29\uC18C\uBA78 \uC704\uC9C0\uC5ED \uC9C0\uC6D0 \uD2B9\uBCC4\uBC95 \uAC1C\uC815 \uBC0F \uC138\uC81C \uD61C\uD0DD \uD6A8\uACFC \uBD84\uC11D",
    mtr_div: "\uC608\uC0B0\uC815\uCC45\uCC98 \uC815\uCC45\uBD84\uC11D",
    wrt_dt: "2026-05-12",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=003"
  },
  {
    source_key: "asm_2026_004",
    mtr_ttl: "\uAC00\uC0C1\uC790\uC0B0 \uC774\uC6A9\uC790 \uBCF4\uD638 \uBC0F \uAC00\uC0C1\uC790\uC0B0\uAC70\uB798\uC18C \uBD88\uACF5\uC815\uAC70\uB798 \uADDC\uC81C \uAD6C\uCCB4\uD654 \uBC29\uC548",
    mtr_div: "\uD1A0\uB860\uD68C \uC790\uB8CC\uC9D1",
    wrt_dt: "2026-02-28",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=004"
  },
  {
    source_key: "asm_2026_005",
    mtr_ttl: "\uCD08\uC911\uACE0 \uAD50\uC721\uACA9\uCC28 \uD574\uC18C\uB97C \uC704\uD55C \uC628\uB77C\uC778 \uACF5\uAD50\uC721 \uD50C\uB7AB\uD3FC \uAD6C\uCD95 \uBC0F \uACE0\uB3C4\uD654",
    mtr_div: "\uC815\uCC45 \uC138\uBBF8\uB098",
    wrt_dt: "2026-05-25",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=005"
  },
  {
    source_key: "asm_2026_006",
    mtr_ttl: "\uBC14\uC774\uC624 \uD5EC\uC2A4\uCF00\uC5B4 \uBBF8\uB798 \uBA39\uAC70\uB9AC \uC0B0\uC5C5 \uC721\uC131\uC744 \uC704\uD55C \uADDC\uC81C \uC0CC\uB4DC\uBC15\uC2A4 \uC815\uCC45",
    mtr_div: "\uC758\uC6D0\uC2E4 \uC785\uBC95 \uC815\uCC45 \uBCF4\uACE0\uC11C",
    wrt_dt: "2026-05-29",
    link_url: "https://www.nars.go.kr/report/view.do?reportId=006"
  }
];
app.post("/api/auth/login.php", (req, res) => {
  const { email, password } = req.body;
  const db = getDB();
  const user = db.users.find((u) => u.email === email);
  if (!user) {
    return res.status(401).json({ ok: false, error: "Invalid email or password" });
  }
  if (password !== "admin123" && password !== "user123" && password !== user.password_hash) {
    return res.status(401).json({ ok: false, error: "Invalid email or password" });
  }
  const token = `session_${user.id}_${Date.now()}`;
  activeSessions[token] = {
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role
  };
  res.json({
    ok: true,
    token,
    // Return token to store client-side inside simulator LocalStorage
    user: {
      id: user.id,
      email: user.email,
      name: user.name,
      role: user.role
    }
  });
});
app.post("/api/auth/register.php", (req, res) => {
  const { name, email, password } = req.body;
  if (!name || !email || !password) {
    return res.status(400).json({ ok: false, error: "All fields are required" });
  }
  const db = getDB();
  if (db.users.some((u) => u.email === email)) {
    return res.status(400).json({ ok: false, error: "Email is already registered" });
  }
  const newUser = {
    id: db.users.length + 1,
    email,
    password_hash: password,
    // simple simulated save
    name,
    role: db.users.length === 0 ? "admin" : "user",
    created_at: (/* @__PURE__ */ new Date()).toISOString().replace("T", " ").substring(0, 19)
  };
  db.users.push(newUser);
  saveDB(db);
  res.json({ ok: true });
});
app.post("/api/auth/logout.php", (req, res) => {
  const authHeader = req.headers["authorization"] || req.headers["x-session-id"];
  if (authHeader) {
    const token = String(authHeader).replace("Bearer ", "");
    delete activeSessions[token];
  }
  res.json({ ok: true });
});
app.get("/api/auth/me.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  res.json({ ok: true, user: me });
});
app.get("/api/assembly/search.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const q = String(req.query.q || "").toLowerCase();
  const db = getDB();
  const filtered = MOCK_MATERIALS_CACHE.filter(
    (item) => !q || item.mtr_ttl.toLowerCase().includes(q) || item.mtr_div.toLowerCase().includes(q)
  );
  const results = filtered.map((item, idx) => {
    let dbItem = db.items.find((i) => i.source_key === item.source_key);
    if (!dbItem) {
      dbItem = {
        id: db.items.length + 100 + idx,
        source: "assembly",
        source_key: item.source_key,
        mtr_ttl: item.mtr_ttl,
        mtr_div: item.mtr_div,
        wrt_dt: item.wrt_dt,
        link_url: item.link_url
      };
      db.items.push(dbItem);
    }
    return dbItem;
  });
  saveDB(db);
  res.json(results);
});
app.get("/api/library/list.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const workspaceId = req.query.workspace_id;
  const db = getDB();
  let entries = db.library_entries;
  if (workspaceId) {
    entries = entries.filter((le) => le.workspace_id === workspaceId);
  }
  const joined = entries.map((entry) => {
    const item = db.items.find((i) => i.id === entry.item_id) || {};
    return {
      id: entry.id,
      workspace_id: entry.workspace_id,
      item_id: entry.item_id,
      mtr_ttl: item.mtr_ttl || "Unknown Document",
      mtr_div: item.mtr_div || "Document",
      wrt_dt: item.wrt_dt || "2026-05-30",
      link_url: item.link_url || "#",
      note: entry.note,
      is_favorite: entry.is_favorite
    };
  });
  res.json(joined);
});
app.post("/api/library/save.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const { workspace_id, item_id, note } = req.body;
  if (!workspace_id || !item_id) {
    return res.status(400).json({ ok: false, error: "Workspace ID and Item ID are required" });
  }
  const db = getDB();
  const existingIdx = db.library_entries.findIndex(
    (le) => le.workspace_id === workspace_id && le.item_id === Number(item_id)
  );
  if (existingIdx >= 0) {
    db.library_entries[existingIdx].note = note;
  } else {
    db.library_entries.push({
      id: db.library_entries.length + 1,
      workspace_id,
      item_id: Number(item_id),
      created_by: me.id,
      note: note || "",
      is_favorite: false,
      created_at: (/* @__PURE__ */ new Date()).toISOString().replace("T", " ").substring(0, 19)
    });
  }
  saveDB(db);
  res.json({ ok: true });
});
app.post("/api/library/delete.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const { id } = req.body;
  if (!id) {
    return res.status(400).json({ ok: false, error: "Entry ID is required" });
  }
  const db = getDB();
  db.library_entries = db.library_entries.filter((le) => le.id !== Number(id));
  saveDB(db);
  res.json({ ok: true });
});
app.post("/api/library/update.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const { id, note } = req.body;
  if (!id) {
    return res.status(400).json({ ok: false, error: "Entry ID is required" });
  }
  const db = getDB();
  const entry = db.library_entries.find((le) => le.id === Number(id));
  if (entry) {
    entry.note = note || "";
    saveDB(db);
    return res.json({ ok: true });
  }
  res.status(404).json({ ok: false, error: "Entry not found" });
});
app.post("/api/library/favorite.php", (req, res) => {
  const me = getMe(req);
  if (!me) {
    return res.status(401).json({ ok: false, error: "Unauthorized" });
  }
  const { id, is_favorite } = req.body;
  if (!id) {
    return res.status(400).json({ ok: false, error: "Entry ID is required" });
  }
  const db = getDB();
  const entry = db.library_entries.find((le) => le.id === Number(id));
  if (entry) {
    entry.is_favorite = Boolean(is_favorite);
    saveDB(db);
    return res.json({ ok: true });
  }
  res.status(404).json({ ok: false, error: "Entry not found" });
});
app.get("/api/admin/users.php", (req, res) => {
  const me = getMe(req);
  if (!me || me.role !== "admin") {
    return res.status(403).json({ ok: false, error: "Admin only" });
  }
  const db = getDB();
  const userList = db.users.map((u) => ({
    id: u.id,
    name: u.name,
    email: u.email,
    role: u.role,
    created_at: u.created_at
  }));
  res.json(userList);
});
app.get("/api/admin/workspaces.php", (req, res) => {
  const me = getMe(req);
  if (!me || me.role !== "admin") {
    return res.status(403).json({ ok: false, error: "Admin only" });
  }
  const db = getDB();
  const list = db.workspaces.map((w) => {
    const creator = db.users.find((u) => u.id === w.created_by);
    return {
      id: w.id,
      name: w.name,
      created_at: w.created_at,
      creator_name: creator ? creator.name : "System"
    };
  });
  res.json(list);
});
app.get("/api/system/stats.json", (req, res) => {
  const db = getDB();
  res.json({
    usersCount: db.users.length,
    itemsCount: db.items.length,
    entriesCount: db.library_entries.length,
    workspacesCount: db.workspaces.length,
    favoritesCount: db.library_entries.filter((le) => le.is_favorite).length
  });
});
async function startServer() {
  if (process.env.NODE_ENV !== "production") {
    const vite = await (0, import_vite.createServer)({
      server: { middlewareMode: true },
      appType: "spa"
    });
    app.use(vite.middlewares);
  } else {
    const distPath = import_path.default.join(process.cwd(), "dist");
    app.use(import_express.default.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(import_path.default.join(distPath, "index.html"));
    });
  }
  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Express simulation server active on port ${PORT}`);
  });
}
startServer();
//# sourceMappingURL=server.cjs.map
