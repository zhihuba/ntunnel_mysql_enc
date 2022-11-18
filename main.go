package main

import (
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"github.com/gin-gonic/gin"
	"github.com/yajunzhang/go_userAgent"
	"io/ioutil"
	"net/http"
	"os"
	"strings"
)

var (
	ua  = go_userAgent.NewUserAgent()
	url string
)

func indexPosition(s string, arr []string) int {
	for i, c := range arr {
		if c == s {
			return i
		}
	}

	return -1
}

// Encode takes a plain text message and returns the rot13 encoded result.
func rot13Encode(message string) string {

	alphabet := []string{"a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z"}
	alphaLen := len(alphabet)

	var encoded []string

	for _, c := range message {
		strC := string(c)
		if strC == " " {
			encoded = append(encoded, " ")

			continue
		}

		isUpper := false
		if strings.ToLower(strC) != strC {
			isUpper = true
		}

		i := indexPosition(strings.ToLower(strC), alphabet)
		if i == -1 {
			encoded = append(encoded, strC)

			continue
		}

		var pos int
		pos = i + 13

		if pos >= alphaLen {
			pos = (i + 13) - alphaLen
		}

		if isUpper {
			encoded = append(encoded, strings.ToUpper(alphabet[pos]))

			continue
		}

		encoded = append(encoded, alphabet[pos])
	}
	rot13rust := strings.Join(encoded, "")

	return hex.EncodeToString([]byte(rot13rust))
}

// Decode takes a rot13 encoded message and returns the unencoded result.
func rot13Decode(message string) string {
	decoderust, err := hex.DecodeString(message)
	if err != nil {
		return ""
	}
	alphabet := []string{"a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z"}
	alphaLen := len(alphabet)

	var decoded []string

	for _, c := range string(decoderust) {
		strC := string(c)
		if strC == " " {
			decoded = append(decoded, " ")

			continue
		}

		isUpper := false
		if strings.ToLower(strC) != strC {
			isUpper = true
		}

		i := indexPosition(strings.ToLower(strC), alphabet)
		if i == -1 {
			decoded = append(decoded, strC)

			continue
		}

		var pos int
		pos = i - 13

		if pos < 0 {
			pos = alphaLen + (i - 13)
		}

		if isUpper {
			decoded = append(decoded, strings.ToUpper(alphabet[pos]))

			continue
		}

		decoded = append(decoded, alphabet[pos])
	}
	rot13Data := strings.Join(decoded, "")
	decodeString, err := hex.DecodeString(rot13Data)
	if err != nil {
		return ""
	}
	return string(decodeString)
}

func postData(url, data string) string {
	client := &http.Client{}

	reqest, err := http.NewRequest("POST", url, strings.NewReader(data))
	if err != nil {
		fmt.Println("Fatal error", err.Error())
	}
	reqest.Header.Add("user-agent", ua.Random())
	reqest.Header.Add("token", "1wedasd")
	//reqest.Header.Add("Cookie", "XDEBUG_SESSION=XDEBUG_ECLIPSE")
	reqest.Header.Add("Content-Type", "application/x-www-form-urlencoded")

	resp, err := client.Do(reqest)
	defer resp.Body.Close()
	content, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		fmt.Println("Fatal error", err.Error())
	}
	return string(content)

}

type sqlData struct {
	Host     string `form:"host"`
	Port     string `form:"port"`
	Login    string `form:"login"`
	Password string `form:"password"`
	Db       string `form:"db"`
	Actn     string `form:"actn"`
}

func sayhelloName(c *gin.Context) {

	m := &sqlData{}
	err := c.Bind(m)
	if err != nil {
		c.String(500, err.Error())
	}
	init, _ := json.Marshal(m)
	initData := string(init)
	if m.Actn == "C" {
		postdat := fmt.Sprintf("data=%s", rot13Encode(initData))

		rust := postData(url, postdat)
		//c.Header("Server", "nginx")
		//c.Header("Content-Type", "text/plain; charset=x-user-defined")
		//c.Header("Vary", "Accept-Encoding")
		c.String(200, rot13Decode(rust))

	} else if m.Actn == "Q" {
		var encdata string
		//sql查询
		query := c.PostFormArray("q[]")
		for i, d := range query {
			if i < 1 {
				encdata = encdata + fmt.Sprintf("data=%s&", rot13Encode(initData))
			}
			if i+1 >= len(query) {

				encdata = encdata + fmt.Sprintf("q[]=%s", rot13Encode(d))

			} else {

				encdata = encdata + fmt.Sprintf("q[]=%s&", rot13Encode(d))

			}
		}
		rust := postData(url, encdata)
		//c.Header("Server", "nginx")
		//c.Header("Content-Type", "text/plain; charset=x-user-defined")
		//c.Header("Vary", "Accept-Encoding")
		returnData := rot13Decode(rust)

		c.Data(200, "text/plain; charset=x-user-defined", []byte(returnData))
		//c.String(200, returnData)

	} else {
		c.String(500, err.Error())

	}

}

func init() {

	flag.StringVar(&url, "u", "", "input ntunnel_mysql.php ")
	flag.Parse()
	if url == "" {
		fmt.Printf("ntunnel_mysql -u http://127.0.0.1/sql.php")
		os.Exit(1)
	}
}
func main() {

	r := gin.Default()
	r.POST("/mysql", sayhelloName)
	err := r.Run(":9090")
	if err != nil {
		panic(err)
	}

}
