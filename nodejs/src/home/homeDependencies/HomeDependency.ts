export class HomeDependency {
    private _randomNumber:number = Math.floor(Math.random() * 10)
    constructor(){}

    get randomNumber() {
        return this._randomNumber
    }

    public logTheRandomNumber() {
        console.log("The random number: " + this.randomNumber)
    }
}